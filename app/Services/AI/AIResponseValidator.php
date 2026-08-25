<?php

namespace App\Services\AI;

use App\Modules\AI\Services\AiSettingsService;
use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Validates and normalizes Gemini NLU / recommendation JSON.
 * Never invents flight prices, availability, or IATA codes.
 */
class AIResponseValidator
{
    public function __construct(
        private readonly ?AiSettingsService $aiSettings = null,
    ) {
    }
    public const INTENTS = [
        'searchFlight',
        'searchHotel',
        'searchInsurance',
        'searchEsim',
        'bookExistingResult',
        'showMoreResults',
        'cancel',
        'askQuestion',
        'viewOrder',
        'viewBookingDetails',
        'unknown',
    ];

    public const PRODUCTS = [
        'flight',
        'hotel',
        'insurance',
        'esim',
        'none',
    ];

    public const CABIN_CLASSES = [
        'economy',
        'premium_economy',
        'business',
        'first',
    ];

    public const SORT_PREFERENCES = [
        'cheapest',
        'fastest',
        'best_value',
        'earliest',
        'latest',
    ];

    public const RECOMMENDATION_LABELS = [
        'best_value',
        'cheapest',
        'fastest',
        'best_direct',
        'best_time',
    ];

    public const FLIGHT_REQUIRED_SLOTS = [
        'origin',
        'destination',
        'departureDate',
        'adults',
    ];

    public const HOTEL_REQUIRED_SLOTS = [
        'hotelCity',
        'checkIn',
        'checkOut',
        'adults',
    ];

    public const INSURANCE_REQUIRED_SLOTS = [
        'zoneName',
        'insuranceStart',
        'insuranceEnd',
        'adults',
    ];

    public const ESIM_REQUIRED_SLOTS = [
        'esimCountry',
    ];

    /**
     * @param  array<string, mixed>|null  $decoded
     * @param  array<string, mixed>|null  $searchContext
     * @return array<string, mixed>
     */
    public function validateIntent(?array $decoded, ?array $searchContext = null, ?DateTimeInterface $currentDate = null): array
    {
        $decoded = is_array($decoded) ? $decoded : [];
        $timezone = $this->aiSettings()->timezone();
        $now = $currentDate
            ? Carbon::parse($currentDate)->timezone($timezone)
            : Carbon::now(new DateTimeZone($timezone));

        $intent = $this->enum($decoded['intent'] ?? null, self::INTENTS, 'unknown');
        $product = $this->enum($decoded['product'] ?? null, self::PRODUCTS, 'none');

        if ($intent === 'searchFlight') {
            $product = 'flight';
        } elseif ($intent === 'searchHotel') {
            $product = 'hotel';
        } elseif ($intent === 'searchInsurance') {
            $product = 'insurance';
        } elseif ($intent === 'searchEsim') {
            $product = 'esim';
        }

        $origin = $this->nullableString($decoded['origin'] ?? null);
        $destination = $this->nullableString($decoded['destination'] ?? null);
        $adults = $this->nullablePositiveInt($decoded['adults'] ?? null);
        $children = $this->nonNegativeInt($decoded['children'] ?? 0, 0);
        $infants = $this->nonNegativeInt($decoded['infants'] ?? 0, 0);
        $cabinClass = $this->enum($decoded['cabinClass'] ?? null, self::CABIN_CLASSES, null);
        $nonStop = $this->nullableBool($decoded['nonStop'] ?? null);
        $airline = $this->nullableString($decoded['airline'] ?? null);
        $departureTimeFrom = $this->nullableTime($decoded['departureTimeFrom'] ?? null);
        $departureTimeTo = $this->nullableTime($decoded['departureTimeTo'] ?? null);
        $budget = $this->nullableNumber($decoded['budget'] ?? null);
        $currency = $this->nullableString($decoded['currency'] ?? null)
            ?? $this->aiSettings()->defaultCurrency();
        $sortPreference = $this->enum($decoded['sortPreference'] ?? null, self::SORT_PREFERENCES, null);

        $hotelCity = $this->nullableString($decoded['hotelCity'] ?? $decoded['hotel_city'] ?? null);
        $rooms = $this->nullablePositiveInt($decoded['rooms'] ?? null);
        $zoneName = $this->nullableString($decoded['zoneName'] ?? $decoded['zone_name'] ?? $decoded['insuranceDestination'] ?? null);
        $esimCountry = $this->nullableString($decoded['esimCountry'] ?? $decoded['esim_country'] ?? null);

        if ($hotelCity === null && $product === 'hotel') {
            $hotelCity = $destination;
        }
        if ($zoneName === null && $product === 'insurance') {
            $zoneName = $destination;
        }
        if ($esimCountry === null && $product === 'esim') {
            $esimCountry = $destination;
        }

        $dateRange = $this->normalizeDateRange($decoded['dateRange'] ?? null);
        $departureDate = $this->resolveDateToken($decoded['departureDate'] ?? null, $now, $dateRange);
        $returnDate = $this->resolveDateToken($decoded['returnDate'] ?? null, $now, null);
        $checkIn = $this->resolveDateToken($decoded['checkIn'] ?? $decoded['check_in'] ?? null, $now, null);
        $checkOut = $this->resolveDateToken($decoded['checkOut'] ?? $decoded['check_out'] ?? null, $now, null);
        $insuranceStart = $this->resolveDateToken($decoded['insuranceStart'] ?? $decoded['insurance_start'] ?? null, $now, null);
        $insuranceEnd = $this->resolveDateToken($decoded['insuranceEnd'] ?? $decoded['insurance_end'] ?? null, $now, null);

        // Merge prior search context for preference-only refinements (no new search).
        if (is_array($searchContext)) {
            $origin = $origin ?? $this->nullableString($searchContext['origin'] ?? null);
            $destination = $destination ?? $this->nullableString($searchContext['destination'] ?? null);
            $departureDate = $departureDate ?? $this->nullableString($searchContext['departureDate'] ?? null);
            $returnDate = $returnDate ?? $this->nullableString($searchContext['returnDate'] ?? null);
            $adults = $adults ?? $this->nullablePositiveInt($searchContext['adults'] ?? null);
            $children = isset($decoded['children'])
                ? $children
                : $this->nonNegativeInt($searchContext['children'] ?? $children, $children);
            $infants = isset($decoded['infants'])
                ? $infants
                : $this->nonNegativeInt($searchContext['infants'] ?? $infants, $infants);
            $cabinClass = $cabinClass ?? $this->enum($searchContext['cabinClass'] ?? null, self::CABIN_CLASSES, null);
            $nonStop = $nonStop ?? $this->nullableBool($searchContext['nonStop'] ?? null);
            $airline = $airline ?? $this->nullableString($searchContext['airline'] ?? null);
            $departureTimeFrom = $departureTimeFrom ?? $this->nullableTime($searchContext['departureTimeFrom'] ?? null);
            $departureTimeTo = $departureTimeTo ?? $this->nullableTime($searchContext['departureTimeTo'] ?? null);
            $budget = $budget ?? $this->nullableNumber($searchContext['budget'] ?? null);
            $sortPreference = $sortPreference ?? $this->enum($searchContext['sortPreference'] ?? null, self::SORT_PREFERENCES, null);
            $hotelCity = $hotelCity ?? $this->nullableString($searchContext['hotelCity'] ?? null);
            $checkIn = $checkIn ?? $this->nullableString($searchContext['checkIn'] ?? null);
            $checkOut = $checkOut ?? $this->nullableString($searchContext['checkOut'] ?? null);
            $rooms = $rooms ?? $this->nullablePositiveInt($searchContext['rooms'] ?? null);
            $zoneName = $zoneName ?? $this->nullableString($searchContext['zoneName'] ?? null);
            $insuranceStart = $insuranceStart ?? $this->nullableString($searchContext['insuranceStart'] ?? null);
            $insuranceEnd = $insuranceEnd ?? $this->nullableString($searchContext['insuranceEnd'] ?? null);
            $esimCountry = $esimCountry ?? $this->nullableString($searchContext['esimCountry'] ?? null);
            if ($product === 'none') {
                $product = $this->enum($searchContext['product'] ?? null, self::PRODUCTS, 'none') ?? 'none';
            }
        }

        $missingSlots = [];
        if (is_array($decoded['missingSlots'] ?? null)) {
            foreach ($decoded['missingSlots'] as $slot) {
                if (is_string($slot) && $slot !== '') {
                    $missingSlots[] = $slot;
                }
            }
        }

        if ($intent === 'askQuestion' || $intent === 'cancel') {
            $missingSlots = [];
            $product = 'none';
        } elseif ($intent === 'searchFlight' || $product === 'flight') {
            $missingSlots = $this->computeMissingSlots(self::FLIGHT_REQUIRED_SLOTS, [
                'origin' => $origin,
                'destination' => $destination,
                'departureDate' => $departureDate,
                'adults' => $adults,
            ], $missingSlots);
        } elseif ($intent === 'searchHotel' || $product === 'hotel') {
            $missingSlots = $this->computeMissingSlots(self::HOTEL_REQUIRED_SLOTS, [
                'hotelCity' => $hotelCity,
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'adults' => $adults,
            ], $missingSlots);
        } elseif ($intent === 'searchInsurance' || $product === 'insurance') {
            $missingSlots = $this->computeMissingSlots(self::INSURANCE_REQUIRED_SLOTS, [
                'zoneName' => $zoneName,
                'insuranceStart' => $insuranceStart,
                'insuranceEnd' => $insuranceEnd,
                'adults' => $adults,
            ], $missingSlots);
        } elseif ($intent === 'searchEsim' || $product === 'esim') {
            $missingSlots = $this->computeMissingSlots(self::ESIM_REQUIRED_SLOTS, [
                'esimCountry' => $esimCountry,
            ], $missingSlots);
        }

        $needsClarification = in_array($intent, ['askQuestion', 'cancel'], true)
            ? false
            : ((bool) ($decoded['needsClarification'] ?? false) || $missingSlots !== []);

        $confidence = $this->clampFloat($decoded['confidence'] ?? 0, 0.0, 1.0);

        $assistantMessage = $this->nullableString($decoded['assistantMessage'] ?? null)
            ?? $this->defaultClarificationMessage($missingSlots);

        $slots = [
            'origin' => $origin,
            'destination' => $destination,
            'departureDate' => $departureDate,
            'returnDate' => $returnDate,
            'dateRange' => $dateRange,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'cabinClass' => $cabinClass,
            'nonStop' => $nonStop,
            'airline' => $airline,
            'departureTimeFrom' => $departureTimeFrom,
            'departureTimeTo' => $departureTimeTo,
            'budget' => $budget,
            'currency' => $currency,
            'sortPreference' => $sortPreference,
            'hotelCity' => $hotelCity,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'rooms' => $rooms,
            'zoneName' => $zoneName,
            'insuranceStart' => $insuranceStart,
            'insuranceEnd' => $insuranceEnd,
            'esimCountry' => $esimCountry,
        ];

        return [
            'intent' => $intent,
            'product' => $product,
            'slots' => $slots,
            'missingSlots' => array_values(array_unique($missingSlots)),
            'needsClarification' => $needsClarification,
            'confidence' => $confidence,
            'assistantMessage' => $assistantMessage ?? '',
            'recommendations' => [],
            'navigationAction' => null,
            'fallback' => false,
            'source' => 'gemini',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @param  list<string>  $allowedOfferIds
     * @return list<array{offerId: string, label: string, reason: string}>
     */
    public function validateRecommendations(?array $decoded, array $allowedOfferIds): array
    {
        $decoded = is_array($decoded) ? $decoded : [];
        $items = $decoded['recommendations'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $allowed = array_fill_keys($allowedOfferIds, true);
        $out = [];
        $seenLabels = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $offerId = $this->nullableString($item['offerId'] ?? $item['offer_id'] ?? null);
            $label = $this->enum($item['label'] ?? null, self::RECOMMENDATION_LABELS, null);
            $reason = $this->nullableString($item['reason'] ?? null) ?? '';

            if ($offerId === null || $label === null) {
                continue;
            }
            if (! isset($allowed[$offerId])) {
                continue;
            }
            if (isset($seenLabels[$label])) {
                continue;
            }

            $seenLabels[$label] = true;
            $out[] = [
                'offerId' => $offerId,
                'label' => $label,
                'reason' => mb_substr($reason, 0, 280),
            ];
        }

        return $out;
    }

    /**
     * Decode model JSON text; strips optional markdown fences.
     *
     * @return array<string, mixed>|null
     */
    public function decodeJsonObject(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<string>  $required
     * @param  array<string, mixed>  $slots
     * @param  list<string>  $hinted
     * @return list<string>
     */
    private function computeMissingSlots(array $required, array $slots, array $hinted): array
    {
        $missing = [];
        foreach ($required as $key) {
            $value = $slots[$key] ?? null;
            if ($value === null || $value === '' || $value === 0) {
                $missing[] = $key;
            }
        }

        $hinted = array_values(array_intersect($hinted, $required));

        return $missing !== [] ? $missing : $hinted;
    }

    /**
     * @param  list<string>  $missingSlots
     */
    private function defaultClarificationMessage(array $missingSlots): ?string
    {
        if ($missingSlots === []) {
            return null;
        }

        $first = $missingSlots[0];

        return match ($first) {
            'origin' => 'أكيد ✈️ من أي مدينة بتسافر؟',
            'destination' => 'وين تحب تسافر؟',
            'departureDate' => 'متى تحب السفر؟',
            'adults' => 'كم شخص راح يسافر؟',
            'hotelCity' => 'في أي مدينة تحب الفندق؟',
            'checkIn' => 'متى تاريخ الدخول للفندق؟',
            'checkOut' => 'متى تاريخ المغادرة من الفندق؟',
            'zoneName' => 'لأي منطقة أو دولة تحتاج التأمين؟',
            'insuranceStart' => 'متى يبدأ التأمين؟',
            'insuranceEnd' => 'متى ينتهي التأمين؟',
            'esimCountry' => 'لأي دولة تحتاج eSIM أو شريحة؟',
            default => 'ممكن توضّح طلبك أكثر؟',
        };
    }

    /**
     * @param  mixed  $range
     * @return array{type: string}|null
     */
    private function normalizeDateRange(mixed $range): ?array
    {
        if (! is_array($range)) {
            return null;
        }

        $type = $this->nullableString($range['type'] ?? null);
        if ($type === null) {
            return null;
        }

        $allowed = [
            'today',
            'tomorrow',
            'day_after_tomorrow',
            'next_week',
            'weekend',
            'this_week',
            'next_month',
        ];

        if (! in_array($type, $allowed, true)) {
            return null;
        }

        return ['type' => $type];
    }

    /**
     * @param  array{type: string}|null  $dateRange
     */
    private function resolveDateToken(mixed $token, Carbon $now, ?array $dateRange): ?string
    {
        if (is_string($token) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($token))) {
            return trim($token);
        }

        $normalized = is_string($token) ? strtolower(trim($token)) : null;

        $relative = $normalized;
        if ($relative === null && $dateRange !== null) {
            $relative = $dateRange['type'];
        }

        if ($relative === null || $relative === '') {
            return null;
        }

        $date = match ($relative) {
            'today', 'اليوم' => $now->copy(),
            'tomorrow', 'بكرة', 'بكره', 'غدوة', 'غدوه', 'غدا', 'غداً', 'باكر' => $now->copy()->addDay(),
            'day_after_tomorrow', 'بعد بكرة', 'بعد غد', 'بعدغد' => $now->copy()->addDays(2),
            'in_2_days', 'بعد يومين' => $now->copy()->addDays(2),
            'next_week', 'الأسبوع الجاي', 'الاسبوع الجاي' => $now->copy()->addDays(7),
            'weekend', 'نهاية الأسبوع', 'نهاية الاسبوع' => $this->nextWeekend($now),
            'next_month', 'الشهر الجاي' => $now->copy()->addMonthNoOverflow(),
            default => null,
        };

        // Weekday names (Arabic / English) — next occurrence, not past.
        if ($date === null) {
            $weekday = $this->weekdayNumber($relative);
            if ($weekday !== null) {
                $date = $now->copy()->next($weekday);
                if ($date->isSameDay($now)) {
                    $date = $now->copy();
                }
            }
        }

        return $date?->toDateString();
    }

    private function nextWeekend(Carbon $now): Carbon
    {
        $friday = $now->copy()->next(Carbon::FRIDAY);
        if ($now->isFriday() || $now->isSaturday() || $now->isSunday()) {
            return $now->copy()->startOfDay();
        }

        return $friday->startOfDay();
    }

    private function weekdayNumber(string $token): ?int
    {
        $map = [
            'sunday' => Carbon::SUNDAY,
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            'الأحد' => Carbon::SUNDAY,
            'الاحد' => Carbon::SUNDAY,
            'الإثنين' => Carbon::MONDAY,
            'الاثنين' => Carbon::MONDAY,
            'الثلاثاء' => Carbon::TUESDAY,
            'الأربعاء' => Carbon::WEDNESDAY,
            'الاربعاء' => Carbon::WEDNESDAY,
            'الخميس' => Carbon::THURSDAY,
            'الجمعة' => Carbon::FRIDAY,
            'السبت' => Carbon::SATURDAY,
        ];

        return $map[$token] ?? null;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function enum(mixed $value, array $allowed, ?string $default): ?string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);
        if ($value === '') {
            return $default;
        }

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= 1 ? $int : null;
    }

    private function nonNegativeInt(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $int = (int) $value;

        return max(0, $int);
    }

    private function nullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function nullableTime(mixed $value): ?string
    {
        $text = $this->nullableString($value);
        if ($text === null) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $text)) {
            return $text;
        }

        return null;
    }

    private function clampFloat(mixed $value, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $min;
        }

        return max($min, min($max, (float) $value));
    }

    private function aiSettings(): AiSettingsService
    {
        return $this->aiSettings ?? app(AiSettingsService::class);
    }
}
