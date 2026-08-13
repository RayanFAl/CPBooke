<?php

namespace App\Services\AI;

use App\Modules\AI\Services\AiSettingsService;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

/**
 * Converts natural language (AR/EN/Libyan dialect) into structured travel intents.
 * Does NOT search, price, or book — BookNow APIs own those steps.
 */
class GeminiTravelAssistantService
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly AIResponseValidator $validator,
        private readonly AiSettingsService $aiSettings,
    ) {
    }

    /**
     * @param  list<array{role?: string, text?: string, content?: string}>  $conversation
     * @param  array<string, mixed>|null  $searchContext
     * @return array<string, mixed>
     */
    public function interpret(string $message, array $conversation = [], ?array $searchContext = null): array
    {
        $timezone = $this->aiSettings->timezone();
        $currentDate = Carbon::now(new DateTimeZone($timezone))->toDateString();

        $maxTurns = $this->aiSettings->maxConversationTurns();
        $trimmedConversation = $this->trimConversation($conversation, $maxTurns);

        $userPrompt = $this->buildUserPrompt(
            $message,
            $trimmedConversation,
            $searchContext,
            $currentDate,
            $timezone,
        );

        try {
            $result = $this->client->generateJson(
                $this->systemInstruction(),
                $userPrompt,
                [
                    'responseSchema' => $this->responseSchema(),
                ],
            );
        } catch (GeminiException $exception) {
            Log::info('Gemini travel assistant fallback', [
                'reason' => $exception->reason,
            ]);

            return $this->fallbackPayload($exception->reason);
        }

        $decoded = $this->validator->decodeJsonObject($result['text']);
        if ($decoded === null) {
            return $this->fallbackPayload(GeminiClient::REASON_INVALID_RESPONSE);
        }

        $validated = $this->validator->validateIntent(
            $decoded,
            $searchContext,
            Carbon::parse($currentDate, $timezone),
        );

        $validated = $this->applyMessageHeuristics($message, $validated);

        $validated['currentDate'] = $currentDate;
        $validated['timezone'] = $timezone;

        return $validated;
    }

    /**
     * Heuristic: prefer Rules NLU for short, clear product phrases to save Free Tier.
     */
    public function shouldPreferRulesNlu(string $message): bool
    {
        $text = trim($message);
        if ($text === '') {
            return true;
        }

        // FAQ / goodbye / multi-product capability → Gemini (or heuristics).
        if ($this->isEndConversation($text) || $this->isCapabilityOrMultiProductFaq($text)) {
            return false;
        }

        // Complex / comparative / multi-constraint utterances → Gemini.
        $complexMarkers = [
            'لكن', 'بس', 'ولو', 'إذا', 'الا اذا', 'إلا إذا',
            'ما نبيش', 'مانبيش', 'مش', 'مو',
            'فرق', 'ميزانية', 'اقل من', 'أقل من',
            'مباشر', 'بدون توقف', 'بزنس', 'درجة',
            'prefer', 'but', 'unless', 'budget', 'difference',
            'non-stop', 'nonstop', 'direct', 'business',
        ];

        $lower = mb_strtolower($text);
        foreach ($complexMarkers as $marker) {
            if (str_contains($lower, mb_strtolower($marker))) {
                return false;
            }
        }

        // Long free-form → Gemini.
        if (mb_strlen($text) > 60) {
            return false;
        }

        // Simple route-like phrases can stay on Rules.
        $simpleRoute = (bool) preg_match(
            '/(طيران|رحلة|رحلات|flight|flights).{0,40}(من|from).{0,40}(ل|الى|إلى|to)/ui',
            $text,
        ) || (bool) preg_match(
            '/(من|from)\s+.+\s+(ل|الى|إلى|to)\s+.+/ui',
            $text,
        );

        $simpleHotel = (bool) preg_match(
            '/(فندق|فنادق|hotel|hotels).{0,40}(في|in|at)\s+.+/ui',
            $text,
        );

        $simpleInsurance = (bool) preg_match(
            '/(تأمين|تامين|insurance).{0,40}(ل|لـ|لـ|to|for)\s+.+/ui',
            $text,
        );

        $simpleEsim = (bool) preg_match(
            '/(esim|e\s*sim|شريحة|شريحه|شريحة\s*انترنت).{0,40}(ل|لـ|to|for)\s+.+/ui',
            $text,
        );

        return $simpleRoute || $simpleHotel || $simpleInsurance || $simpleEsim;
    }

    private function systemInstruction(): string
    {
        return <<<'PROMPT'
You are BookNow travel NLU for Libya. Output JSON only. Never invent prices, availability, IATA codes, airlines, seats, or bookings.

Language: Arabic, English, Libyan dialect (نبي، غدوة، بكرة، شن، قداش، ما نبيش، مش، مو، شفر=eSIM، سيت=seat، انشورانس=insurance).
Normalize common typos: اسطنبول→إسطنبول، تامين→تأمين، شريحه→شريحة، الى/لـ = destination markers.

=== INTENTS ===
searchFlight | searchHotel | searchInsurance | searchEsim | bookExistingResult | showMoreResults | cancel | askQuestion | viewOrder | viewBookingDetails | unknown

=== PRODUCTS ===
flight | hotel | insurance | esim | none

=== WHEN TO USE EACH INTENT ===
- searchFlight: user wants to FIND/BOOK a specific flight trip (has or will give cities/dates).
- searchHotel / searchInsurance / searchEsim: same for that product.
- askQuestion: capability FAQ, how-it-works, multi-product without route/date, what BookNow offers, seat+addons questions.
- cancel: goodbye, thanks-only, stop, end chat, never mind.
- bookExistingResult: user wants to book an already shown/selected offer.
- showMoreResults: more options from current results.
- viewOrder / viewBookingDetails: open orders.
- unknown: truly unclear.

=== REQUIRED SLOTS (only when clearly searching) ===
- flight: origin, destination, departureDate, adults
- hotel: hotelCity, checkIn, checkOut, adults
- insurance: zoneName, insuranceStart, insuranceEnd, adults
- esim: esimCountry
Seat/سيت/مقعد is NOT a product — it is a flight booking addon.

=== HARD RULES ===
1) No concrete route/date → NEVER invent missingSlots for origin/destination/dates on FAQ/capability questions.
2) Multi-product without search details → askQuestion, product=none, missingSlots=[].
3) Short slot answers (بكرة، غدوة، طرابلس، شخصين) fill the ACTIVE search from conversation/searchContext — do not restart as unknown.
4) Never re-ask slots already present in searchContext/conversation.
5) Relative dates use currentDate/timezone from the user payload; prefer YYYY-MM-DD.
6) assistantMessage: clarification only if needsClarification; helpful answer for askQuestion; short goodbye for cancel; else empty string.
7) confidence 0..1. currency default LYD. cabinClass: economy|premium_economy|business|first. sortPreference: cheapest|fastest|best_value|earliest|latest.

=== COMMON MISTAKES — STUDY THESE (WRONG → RIGHT) ===

WRONG: "هل نقدر نحجز طيران مع تأمين و شفر؟" → searchFlight + missing origin/destination
RIGHT: askQuestion, product none, missingSlots [], assistantMessage explains yes + step-by-step

WRONG: "يجيب رحلة مع شريحة مع سيت مع انشورانس ترافل بكل سهولة" → searchFlight
RIGHT: askQuestion — flights + seat during booking + insurance + eSIM, step by step

WRONG: "تقدرون تحجزوا طيران وتأمين؟" → searchFlight
RIGHT: askQuestion

WRONG: "هل في عندكم eSIM؟" / "عندكم شرائح؟" / "هل تأمين سفر؟" → searchEsim/searchInsurance with missing country
RIGHT: askQuestion affirming availability, ask which country/zone if they want to start

WRONG: "شن تسوي؟" / "وش تقدر تسوي؟" / "what can you do?" → unknown or searchFlight
RIGHT: askQuestion listing flights, hotels, insurance, eSIM

WRONG: "نبي طيران مع فندق" (no cities/dates) → searchFlight missing slots
RIGHT: askQuestion — we can do both step by step; what first?

WRONG: "مع السلامة" / "شكرا" / "باي" / "انتهينا" / "كفاية" / "خلاص" / "bye" → searchFlight or unknown asking for cities
RIGHT: cancel, product none, short goodbye

WRONG: "انهي المحادثة" / "سكر الشات" / "ما نبيش حاجة" → searchFlight
RIGHT: cancel

WRONG: conversation has origin+destination, user says "بكرة" → ask again for origin
RIGHT: searchFlight, fill departureDate from بكرة→tomorrow ISO, keep known slots

WRONG: "نبي بزنس وما نبيش الصبح بدري" with existing search → new empty search
RIGHT: same searchFlight, cabinClass=business, departureTimeFrom set, keep origin/destination/date

WRONG: "شفر لتركيا" → searchFlight
RIGHT: searchEsim, esimCountry=تركيا

WRONG: "انشورانس لأوروبا" → searchFlight
RIGHT: searchInsurance, zoneName=أوروبا

WRONG: "فندق في طرابلس" → searchFlight
RIGHT: searchHotel, hotelCity=طرابلس

WRONG: "احجز" after results shown → searchFlight restart
RIGHT: bookExistingResult

WRONG: "عرض المزيد" / "شوفهم" → unknown
RIGHT: showMoreResults

WRONG: "طلباتي" / "حجزي" → askQuestion
RIGHT: viewOrder

WRONG: "قداش التذكرة؟" with no offer selected → invent a price
RIGHT: askQuestion saying choose an offer first / search first — NEVER invent price

WRONG: "سيت نافذة" alone with no active flight search → searchFlight missing all slots
RIGHT: askQuestion — seat is chosen during flight booking; start with a flight search

WRONG: Treat "هل" questions that include "من طرابلس لتونس غدوة" as askQuestion
RIGHT: that HAS route+date → searchFlight with slots filled

WRONG: Put full FAQ sentence into origin or destination fields
RIGHT: leave origin/destination null for askQuestion/cancel

WRONG: "من موقعي لتونس" → origin="موقعي" then treat it as a city to look up in airport search
RIGHT: searchFlight, origin="موقعي" (keep this token). Client resolves GPS → nearest airport. Do NOT map موقعي to a random city.

=== GOOD SEARCH EXAMPLES (do extract slots) ===
"نبي طيران من طرابلس لإسطنبول غدوة شخصين" → searchFlight
"نبي نسافر من موقعي لتونس غدوة صباحا شخص واحد" → searchFlight, origin=موقعي, destination=تونس
"فندق في تونس من بكرة لبعد بكرة" → searchHotel
"تأمين سفر لأوروبا من غدوة لمدة أسبوع" → searchInsurance
"شريحة لتركيا" / "شفر لمصر" → searchEsim
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyMessageHeuristics(string $message, array $validated): array
    {
        if ($this->isEndConversation($message)) {
            return [
                ...$validated,
                'intent' => 'cancel',
                'product' => 'none',
                'slots' => $this->emptySlots(),
                'missingSlots' => [],
                'needsClarification' => false,
                'confidence' => max(0.9, (float) ($validated['confidence'] ?? 0)),
                'assistantMessage' => $this->endConversationMessage(
                    (string) ($validated['assistantMessage'] ?? ''),
                ),
            ];
        }

        if (! $this->isCapabilityOrMultiProductFaq($message)) {
            return $validated;
        }

        $assistantMessage = trim((string) ($validated['assistantMessage'] ?? ''));

        return [
            ...$validated,
            'intent' => 'askQuestion',
            'product' => 'none',
            'slots' => $this->emptySlots(),
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => max(0.85, (float) ($validated['confidence'] ?? 0)),
            'assistantMessage' => $this->capabilityAssistantMessage($assistantMessage, $message),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySlots(): array
    {
        return [
            'origin' => null,
            'destination' => null,
            'departureDate' => null,
            'returnDate' => null,
            'dateRange' => null,
            'adults' => null,
            'children' => 0,
            'infants' => 0,
            'cabinClass' => null,
            'nonStop' => null,
            'airline' => null,
            'departureTimeFrom' => null,
            'departureTimeTo' => null,
            'budget' => null,
            'currency' => $this->aiSettings->defaultCurrency(),
            'sortPreference' => null,
            'hotelCity' => null,
            'checkIn' => null,
            'checkOut' => null,
            'rooms' => null,
            'zoneName' => null,
            'insuranceStart' => null,
            'insuranceEnd' => null,
            'esimCountry' => null,
        ];
    }

    private function capabilityAssistantMessage(string $fromModel, string $userMessage = ''): string
    {
        $fromModel = trim($fromModel);
        // Reject clarification-style replies for capability FAQs.
        if ($fromModel !== '' &&
            ! preg_match('/(من وين|وين تبي|where (are|do)|how many|كم عدد|تاريخ السفر|من أي مدينة)/ui', $fromModel)
        ) {
            return $fromModel;
        }

        $lower = mb_strtolower($userMessage);
        $mentionsSeat = (bool) preg_match('/(سيت|seat|مقعد|مقاعد)/ui', $lower);
        $mentionsEsim = (bool) preg_match('/(esim|e\s*sim|شريحة|شريحه|شفر)/ui', $lower);
        $mentionsInsurance = (bool) preg_match('/(تأمين|تامين|insurance|انشورانس|انشورنس)/ui', $lower);

        if ($mentionsSeat || ($mentionsEsim && $mentionsInsurance)) {
            return 'أيوه، تقدر تحجز رحلة، وتختار مقعد أثناء الحجز، وتضيف تأمين سفر وشريحة eSIM — نكمّلهم خطوة بخطوة بكل سهولة. شنو تبي نبدأ بيه؟';
        }

        if ((bool) preg_match('/(شن تسوي|وش تقدر|شنو تقدر|ماذا تفعل|what can you do|ايش تقدر)/ui', $lower)) {
            return 'أقدر أساعدك في طيران، فنادق، تأمين سفر، وشريحة eSIM. شنو تبي نبدأ بيه؟';
        }

        if ((bool) preg_match('/(قداش|كم السعر|سعر|how much)/ui', $lower)) {
            return 'الأسعار تجي من نتائج البحث الحقيقية بعد ما نحدد الرحلة أو المنتج — ما نخترع أسعار. نبدأ بحث؟';
        }

        return 'أيوه، تقدر تحجز طيران وتأمين سفر وشريحة eSIM من BookNow. نقدر نكمّلهم خطوة بخطوة — شنو تبي نبدأ بيه؟';
    }

    private function endConversationMessage(string $fromModel): string
    {
        $fromModel = trim($fromModel);
        if ($fromModel !== '' &&
            ! preg_match('/(من وين|وين تبي|من أي|how many|كم )/ui', $fromModel)
        ) {
            return $fromModel;
        }

        return 'تمام، إذا احتجت أي حاجة أنا هنا. سلامة!';
    }

    private function isEndConversation(string $message): bool
    {
        $text = mb_strtolower(trim($message));
        if ($text === '') {
            return false;
        }

        // Short goodbye / stop phrases only — avoid matching mid-search "خلاص كذا".
        $exactOrShort = [
            'وداع',
            'وداعا',
            'وداعاً',
            'مع السلامة',
            'مع السلامه',
            'باي',
            'bye',
            'goodbye',
            'good bye',
            'see you',
            'thanks',
            'thank you',
            'thx',
            'شكرا',
            'شكراً',
            'شكرا جزيلا',
            'شكراً جزيلاً',
            'مشكور',
            'مشكورة',
            'يسلمو',
            'يسلموا',
            'انتهينا',
            'انتهى',
            'كفاية',
            'كفايه',
            'خلاص',
            'وقف',
            'stop',
            'cancel',
            'never mind',
            'nevermind',
            'الغاء',
            'إلغاء',
            'الغِ',
            'بلاش',
            'ما نبيش',
            'مانبيش',
            'لا شكرا',
            'لا شكراً',
        ];

        foreach ($exactOrShort as $phrase) {
            if ($text === mb_strtolower($phrase)) {
                return true;
            }
        }

        // Soft enders when the whole utterance is basically goodbye/thanks.
        if (mb_strlen($text) <= 40 && preg_match(
            '/^(تمام[,،]?\s*)?(شكراً?|يسلمو|مع السلامة|وداعا?ً?|باي|bye|thanks|thank you)\b/ui',
            $text,
        )) {
            return true;
        }

        return (bool) preg_match(
            '/(انهي|إنهي|انهي المحادثة|إنهاء|اقفل|أقفل|سكر|سكّر|خلصت|خلّصت|ما نبيش حاجة|مانبيش حاجة)/ui',
            $text,
        );
    }

    private function isCapabilityOrMultiProductFaq(string $message): bool
    {
        $text = mb_strtolower(trim($message));
        if ($text === '') {
            return false;
        }

        if ($this->hasConcreteSearchDetails($message)) {
            return false;
        }

        $capabilityMarkers = [
            'هل نقدر',
            'نقدر نحجز',
            'نقدر حجز',
            'نقدر نجيب',
            'هل يمكن',
            'هل نقدر نحجز',
            'هل نقدر حجز',
            'هل نستطيع',
            'can we book',
            'can i book',
            'is it possible',
            'do you offer',
            'what can you do',
            'how does it work',
            'هل عندكم',
            'هل تقدرون',
            'هل تقدرو',
            'هل تقدر',
            'هل في',
            'تقدرون',
            'تقدرو',
            'تقدر تحجز',
            'تقدر تجيب',
            'يقدر يجيب',
            'يستطيع',
            'يستطع',
            'نجيب رحلة',
            'نجيب طيران',
            'جلب رحلة',
            'يجيب رحلة',
            'يجيب طيران',
            'بكل سهولة',
            'بسهولة',
            'easy to book',
            'in one go',
            'together',
            'مع بعض',
            'في نفس الوقت',
            'شن تسوي',
            'شن تسوى',
            'وش تقدر',
            'وش تسوي',
            'شنو تقدر',
            'شنو تسوي',
            'ماذا تفعل',
            'ايش تقدر',
            'كيف تحجز',
            'كيف نحجز',
            'قداش التذكرة',
            'كم السعر',
            'سعر التذكرة',
            'how much',
            'seat selection',
            'اختيار مقعد',
            'مقعد نافذة',
            'سيت نافذة',
            'طيران مع فندق',
            'طيران وتأمين',
            'رحلة مع',
            'مع شفر',
            'مع شريحة',
            'مع تأمين',
            'مع انشورانس',
            'عندكم esim',
            'عندكم شرائح',
            'عندكم تأمين',
            'عندكم طيران',
        ];

        $hasCapability = false;
        foreach ($capabilityMarkers as $marker) {
            if (str_contains($text, mb_strtolower($marker))) {
                $hasCapability = true;
                break;
            }
        }

        $productHits = 0;
        if (preg_match('/(طيران|رحلة|رحلات|flight|flights)/ui', $text)) {
            $productHits++;
        }
        if (preg_match('/(تأمين|تامين|insurance|انشورانس|انشورنس)/ui', $text)) {
            $productHits++;
        }
        if (preg_match('/(esim|e\s*sim|شريحة|شريحه|شفر)/ui', $text)) {
            $productHits++;
        }
        if (preg_match('/(فندق|فنادق|hotel|hotels)/ui', $text)) {
            $productHits++;
        }
        // Seat is an addon signal for multi-product FAQ, not a standalone product.
        $mentionsSeat = (bool) preg_match('/(سيت|seat|مقعد|مقاعد)/ui', $text);

        if ($hasCapability) {
            return true;
        }

        // "رحلة مع شريحة مع سيت مع انشورانس" without route/date → FAQ, not search.
        if ($productHits >= 2) {
            return true;
        }

        // Seat-only without search details → FAQ (seat is a booking addon).
        return $mentionsSeat;
    }

    private function hasConcreteSearchDetails(string $message): bool
    {
        $hasRoute = (bool) preg_match(
            '/(من|from)\s+\S+\s+(ل|الى|إلى|to)\s+\S+/ui',
            $message,
        );
        $hasDate = (bool) preg_match(
            '/(بكرة|بكره|غدوة|غدوه|غدا|غداً|tomorrow|next week|\d{4}-\d{2}-\d{2})/ui',
            $message,
        );

        // A named destination alone (e.g. "طيران لإسطنبول غدوة") is a search.
        $hasCityish = (bool) preg_match(
            '/(ل|الى|إلى|to|في|in)\s+[ء-يA-Za-z]{3,}/ui',
            $message,
        );

        return $hasRoute || ($hasDate && $hasCityish) || ($hasDate && (bool) preg_match('/(طيران|رحلة|فندق|flight|hotel)/ui', $message));
    }

    /**
     * @param  list<array{role?: string, text?: string, content?: string}>  $conversation
     * @param  array<string, mixed>|null  $searchContext
     */
    private function buildUserPrompt(
        string $message,
        array $conversation,
        ?array $searchContext,
        string $currentDate,
        string $timezone,
    ): string {
        $payload = [
            'currentDate' => $currentDate,
            'timezone' => $timezone,
            'message' => $message,
            'conversation' => $conversation,
            'searchContext' => $searchContext,
        ];

        return 'Extract structured travel intent from this BookNow request. JSON only.'."\n"
            ."Avoid common mistakes: FAQ/multi-product without route ≠ searchFlight; goodbye/thanks ≠ search; شفر=eSIM; سيت=seat addon; انشورانس=insurance; never put FAQ text into origin/destination; never invent prices.\n"
            .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  list<array{role?: string, text?: string, content?: string}>  $conversation
     * @return list<array{role: string, text: string}>
     */
    private function trimConversation(array $conversation, int $maxTurns): array
    {
        if ($maxTurns <= 0 || $conversation === []) {
            return [];
        }

        $normalized = [];
        foreach ($conversation as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $role = (string) ($turn['role'] ?? 'user');
            $text = (string) ($turn['text'] ?? $turn['content'] ?? '');
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $normalized[] = [
                'role' => $role,
                'text' => mb_substr($text, 0, 400),
            ];
        }

        if (count($normalized) > $maxTurns) {
            $normalized = array_slice($normalized, -$maxTurns);
        }

        return array_values($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'intent' => ['type' => 'STRING'],
                'product' => ['type' => 'STRING'],
                'origin' => ['type' => 'STRING', 'nullable' => true],
                'destination' => ['type' => 'STRING', 'nullable' => true],
                'departureDate' => ['type' => 'STRING', 'nullable' => true],
                'returnDate' => ['type' => 'STRING', 'nullable' => true],
                'dateRange' => [
                    'type' => 'OBJECT',
                    'nullable' => true,
                    'properties' => [
                        'type' => ['type' => 'STRING'],
                    ],
                ],
                'adults' => ['type' => 'INTEGER', 'nullable' => true],
                'children' => ['type' => 'INTEGER', 'nullable' => true],
                'infants' => ['type' => 'INTEGER', 'nullable' => true],
                'cabinClass' => ['type' => 'STRING', 'nullable' => true],
                'nonStop' => ['type' => 'BOOLEAN', 'nullable' => true],
                'airline' => ['type' => 'STRING', 'nullable' => true],
                'departureTimeFrom' => ['type' => 'STRING', 'nullable' => true],
                'departureTimeTo' => ['type' => 'STRING', 'nullable' => true],
                'budget' => ['type' => 'NUMBER', 'nullable' => true],
                'currency' => ['type' => 'STRING', 'nullable' => true],
                'sortPreference' => ['type' => 'STRING', 'nullable' => true],
                'hotelCity' => ['type' => 'STRING', 'nullable' => true],
                'checkIn' => ['type' => 'STRING', 'nullable' => true],
                'checkOut' => ['type' => 'STRING', 'nullable' => true],
                'rooms' => ['type' => 'INTEGER', 'nullable' => true],
                'zoneName' => ['type' => 'STRING', 'nullable' => true],
                'insuranceStart' => ['type' => 'STRING', 'nullable' => true],
                'insuranceEnd' => ['type' => 'STRING', 'nullable' => true],
                'esimCountry' => ['type' => 'STRING', 'nullable' => true],
                'missingSlots' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'needsClarification' => ['type' => 'BOOLEAN'],
                'confidence' => ['type' => 'NUMBER'],
                'assistantMessage' => ['type' => 'STRING'],
            ],
            'required' => [
                'intent',
                'product',
                'missingSlots',
                'needsClarification',
                'confidence',
                'assistantMessage',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackPayload(string $reason): array
    {
        return [
            'intent' => 'unknown',
            'product' => 'none',
            'slots' => [
                'origin' => null,
                'destination' => null,
                'departureDate' => null,
                'returnDate' => null,
                'dateRange' => null,
                'adults' => null,
                'children' => 0,
                'infants' => 0,
                'cabinClass' => null,
                'nonStop' => null,
                'airline' => null,
                'departureTimeFrom' => null,
                'departureTimeTo' => null,
                'budget' => null,
                'currency' => $this->aiSettings->defaultCurrency(),
                'sortPreference' => null,
                'hotelCity' => null,
                'checkIn' => null,
                'checkOut' => null,
                'rooms' => null,
                'zoneName' => null,
                'insuranceStart' => null,
                'insuranceEnd' => null,
                'esimCountry' => null,
            ],
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => 0,
            'assistantMessage' => '',
            'recommendations' => [],
            'navigationAction' => null,
            'fallback' => true,
            'fallbackReason' => $reason,
            'source' => 'fallback',
        ];
    }
}
