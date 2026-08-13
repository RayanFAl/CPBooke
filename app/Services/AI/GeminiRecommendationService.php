<?php

namespace App\Services\AI;

use App\Modules\AI\Services\AiSettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Ranks and explains REAL flight offers from BookNow APIs.
 * Never mutates price, airline, duration, times, stops, or offerId.
 */
class GeminiRecommendationService
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly AIResponseValidator $validator,
        private readonly AiSettingsService $aiSettings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, mixed>  $preferences
     * @param  list<array<string, mixed>>  $offers
     * @return list<array{offerId: string, label: string, reason: string}>
     */
    public function recommend(array $search, array $preferences, array $offers): array
    {
        $maxOffers = $this->aiSettings->maxOffersForRecommendation();
        $trimmedOffers = array_slice($this->sanitizeOffers($offers), 0, $maxOffers);

        if ($trimmedOffers === []) {
            return [];
        }

        $allowedIds = array_values(array_map(
            static fn (array $offer): string => (string) $offer['offerId'],
            $trimmedOffers,
        ));

        $payload = [
            'search' => [
                'origin' => $search['origin'] ?? null,
                'destination' => $search['destination'] ?? null,
                'adults' => $search['adults'] ?? null,
                'departureDate' => $search['departureDate'] ?? null,
            ],
            'preferences' => [
                'priority' => $preferences['priority'] ?? $preferences['sortPreference'] ?? null,
                'nonStop' => $preferences['nonStop'] ?? null,
                'cabinClass' => $preferences['cabinClass'] ?? null,
                'departureTimeFrom' => $preferences['departureTimeFrom'] ?? null,
                'departureTimeTo' => $preferences['departureTimeTo'] ?? null,
                'airline' => $preferences['airline'] ?? null,
                'budget' => $preferences['budget'] ?? null,
            ],
            'offers' => $trimmedOffers,
        ];

        try {
            $result = $this->client->generateJson(
                $this->systemInstruction(),
                'Rank these REAL BookNow flight offers. JSON only.'."\n"
                    .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                [
                    'responseSchema' => $this->responseSchema(),
                    'maxOutputTokens' => 512,
                ],
            );
        } catch (GeminiException $exception) {
            Log::info('Gemini recommendation fallback', [
                'reason' => $exception->reason,
            ]);

            return $this->localFallbackRecommendations($trimmedOffers, $preferences);
        }

        $decoded = $this->validator->decodeJsonObject($result['text']);
        $validated = $this->validator->validateRecommendations($decoded, $allowedIds);

        if ($validated === []) {
            return $this->localFallbackRecommendations($trimmedOffers, $preferences);
        }

        return $validated;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    private function sanitizeOffers(array $offers): array
    {
        $out = [];

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $offerId = $offer['offerId'] ?? $offer['offer_id'] ?? $offer['id'] ?? null;
            if ($offerId === null || $offerId === '') {
                continue;
            }

            $out[] = [
                'offerId' => (string) $offerId,
                'airline' => (string) ($offer['airline'] ?? ''),
                'price' => is_numeric($offer['price'] ?? null) ? (float) $offer['price'] : null,
                'currency' => (string) ($offer['currency'] ?? $this->aiSettings->defaultCurrency()),
                'stops' => is_numeric($offer['stops'] ?? null) ? (int) $offer['stops'] : null,
                'departure' => (string) ($offer['departure'] ?? ''),
                'arrival' => (string) ($offer['arrival'] ?? ''),
                'duration' => (string) ($offer['duration'] ?? ''),
            ];
        }

        return $out;
    }

    private function systemInstruction(): string
    {
        return <<<'PROMPT'
You are BookNow flight recommendation assistant.

Hard rules:
- Use ONLY the offers provided. Never invent or alter price, currency, airline, duration, departure, arrival, stops, or offerId.
- Pick among provided offerIds only.
- Return short Arabic reasons that reference only provided facts.
- Labels allowed: best_value, cheapest, fastest, best_direct, best_time.
- Prefer at most 3 recommendations with distinct labels.
- If preferences ask for cheapest / nonStop / time window, respect them using provided fields only.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'recommendations' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'offerId' => ['type' => 'STRING'],
                            'label' => ['type' => 'STRING'],
                            'reason' => ['type' => 'STRING'],
                        ],
                        'required' => ['offerId', 'label', 'reason'],
                    ],
                ],
            ],
            'required' => ['recommendations'],
        ];
    }

    /**
     * Deterministic ranking when Gemini is unavailable — still based on real offer fields only.
     *
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $preferences
     * @return list<array{offerId: string, label: string, reason: string}>
     */
    private function localFallbackRecommendations(array $offers, array $preferences): array
    {
        if ($offers === []) {
            return [];
        }

        $byPrice = $offers;
        usort($byPrice, static function (array $a, array $b): int {
            return ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX);
        });

        $byDuration = $offers;
        usort($byDuration, static function (array $a, array $b): int {
            return self::durationMinutes($a['duration'] ?? '') <=> self::durationMinutes($b['duration'] ?? '');
        });

        $direct = array_values(array_filter(
            $offers,
            static fn (array $o): bool => ($o['stops'] ?? 1) === 0,
        ));

        $out = [];
        $cheapest = $byPrice[0] ?? null;
        if ($cheapest !== null) {
            $out[] = [
                'offerId' => (string) $cheapest['offerId'],
                'label' => 'cheapest',
                'reason' => 'أقل سعر ضمن النتائج المتاحة',
            ];
        }

        $fastest = $byDuration[0] ?? null;
        if ($fastest !== null && ($cheapest === null || $fastest['offerId'] !== $cheapest['offerId'])) {
            $out[] = [
                'offerId' => (string) $fastest['offerId'],
                'label' => 'fastest',
                'reason' => 'أقصر مدة رحلة ضمن النتائج',
            ];
        }

        if ($direct !== []) {
            $bestDirect = $direct[0];
            usort($direct, static function (array $a, array $b): int {
                return ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX);
            });
            $bestDirect = $direct[0];
            $already = array_column($out, 'offerId');
            if (! in_array($bestDirect['offerId'], $already, true)) {
                $out[] = [
                    'offerId' => (string) $bestDirect['offerId'],
                    'label' => 'best_direct',
                    'reason' => 'رحلة مباشرة من النتائج الحقيقية',
                ];
            }
        }

        $priority = $preferences['priority'] ?? $preferences['sortPreference'] ?? null;
        if ($priority === 'best_value' && $cheapest !== null) {
            array_unshift($out, [
                'offerId' => (string) $cheapest['offerId'],
                'label' => 'best_value',
                'reason' => 'توازن مناسب بين السعر والراحة ضمن النتائج',
            ]);
        }

        // Unique by label, max 3.
        $unique = [];
        $seen = [];
        foreach ($out as $item) {
            if (isset($seen[$item['label']])) {
                continue;
            }
            $seen[$item['label']] = true;
            $unique[] = $item;
            if (count($unique) >= 3) {
                break;
            }
        }

        return $unique;
    }

    private static function durationMinutes(string $duration): int
    {
        if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?$/i', $duration, $m)) {
            return ((int) ($m[1] ?? 0)) * 60 + ((int) ($m[2] ?? 0));
        }

        if (preg_match('/^(\d+):(\d+)$/', $duration, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        return PHP_INT_MAX;
    }
}
