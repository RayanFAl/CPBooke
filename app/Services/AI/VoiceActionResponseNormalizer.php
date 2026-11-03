<?php

namespace App\Services\AI;

/**
 * Mirrors the mobile VoiceActionResponseValidator contract.
 * Never returns confirm_booking / pay. Indices stay 1-based.
 */
class VoiceActionResponseNormalizer
{
    public const INTENTS = ['new_search', 'result_action', 'clarify'];

    public const SERVICE_TYPES = ['flight', 'hotel', 'insurance', 'esim'];

    public const ACTION_TYPES = [
        'select_result',
        'sort_results',
        'go_back',
        'compare_results',
        'cancel',
        'open_results',
        'none',
    ];

    public const CRITERIA = ['price', 'duration', 'rating'];

    /**
     * @param  array<string, mixed>|null  $decoded
     * @param  array<string, mixed>  $actionContext
     * @return array<string, mixed>
     */
    public function normalize(?array $decoded, array $actionContext = []): array
    {
        $decoded = is_array($decoded) ? $decoded : [];

        if ($this->containsForbidden($decoded)) {
            return $this->clarify(
                'Booking/payment cannot be confirmed from a single voice command.',
                $this->serviceType($decoded['service_type'] ?? $decoded['serviceType'] ?? null),
                0.99,
            );
        }

        $intent = $this->enum($decoded['intent'] ?? null, self::INTENTS, 'clarify');
        $service = $this->serviceType($decoded['service_type'] ?? $decoded['serviceType'] ?? null)
            ?? $this->serviceType($actionContext['service_type'] ?? null);
        $slots = $this->slots(is_array($decoded['slots'] ?? null) ? $decoded['slots'] : []);
        $action = $this->action(is_array($decoded['action'] ?? null) ? $decoded['action'] : []);
        $confidence = $this->confidence($decoded['confidence'] ?? 0);
        $reason = $this->nullableString($decoded['clarify_reason'] ?? $decoded['clarifyReason'] ?? null);

        if ($intent === 'clarify') {
            return $this->clarify(
                $reason ?? 'Need more information to continue.',
                $service,
                $confidence,
                $slots,
            );
        }

        $results = is_array($actionContext['displayed_results'] ?? null)
            ? array_values($actionContext['displayed_results'])
            : [];
        $max = count($results);
        $needsResults = in_array($action['type'], [
            'select_result',
            'sort_results',
            'compare_results',
            'open_results',
        ], true);

        if ($intent === 'result_action' && $needsResults && $max === 0) {
            return $this->clarify(
                $reason ?? 'لا توجد نتائج معروضة حالياً يمكن التصرف عليها.',
                $service,
                max($confidence, 0.9),
                $slots,
            );
        }

        $targetIndex = $action['target_index'];
        if ($max > 0 && $targetIndex !== null && ($targetIndex < 1 || $targetIndex > $max)) {
            return $this->clarify(
                'الرقم المطلوب خارج النتائج المعروضة (1–'.$max.').',
                $service,
                0.95,
                $slots,
            );
        }

        $indices = $action['target_indices'];
        if ($max > 0 && is_array($indices)) {
            $indices = array_values(array_filter(
                $indices,
                fn (int $index): bool => $index >= 1 && $index <= $max,
            ));
        }

        if ($action['type'] === 'compare_results' && count($indices ?? []) < 2) {
            return $this->clarify(
                'حدد رقمين على الأقل للمقارنة.',
                $service,
                0.9,
                $slots,
            );
        }

        if ($max === 0) {
            $targetIndex = null;
            $indices = null;
        }

        return $this->payload(
            intent: $intent,
            confidence: $confidence,
            service: $service,
            slots: $slots,
            actionType: $action['type'],
            targetIndex: $targetIndex,
            targetIndices: $indices,
            criteria: $action['criteria'],
            clarifyReason: $reason,
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function containsForbidden(array $decoded): bool
    {
        $blob = strtolower((string) json_encode($decoded));
        foreach (['confirm_booking', 'confirmbooking', '"pay"', 'action":"pay', 'type":"pay'] as $banned) {
            if (str_contains($blob, $banned)) {
                return true;
            }
        }

        $type = strtolower((string) data_get($decoded, 'action.type', ''));

        return in_array($type, ['confirm_booking', 'pay', 'confirm', 'payment'], true);
    }

    /**
     * @param  array<string, mixed>  $slots
     * @return array<string, mixed>
     */
    private function slots(array $slots): array
    {
        $passengers = $slots['passengers'] ?? null;
        if (is_numeric($passengers)) {
            $passengers = max(1, (int) $passengers);
        } else {
            $passengers = null;
        }

        return array_filter([
            'origin' => $this->nullableString($slots['origin'] ?? null),
            'destination' => $this->nullableString($slots['destination'] ?? null),
            'raw_date' => $this->nullableString($slots['raw_date'] ?? $slots['rawDate'] ?? null),
            'passengers' => $passengers,
            'notes' => $this->nullableString($slots['notes'] ?? null),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{type: string, target_index: int|null, target_indices: list<int>|null, criteria: string|null}
     */
    private function action(array $action): array
    {
        $indices = [];
        $rawIndices = $action['target_indices'] ?? $action['targetIndices'] ?? null;
        if (is_array($rawIndices)) {
            foreach ($rawIndices as $item) {
                if (is_numeric($item)) {
                    $indices[] = (int) $item;
                }
            }
        }

        $targetIndex = $action['target_index'] ?? $action['targetIndex'] ?? null;

        return [
            'type' => $this->enum($action['type'] ?? null, self::ACTION_TYPES, 'none') ?? 'none',
            'target_index' => is_numeric($targetIndex) ? (int) $targetIndex : null,
            'target_indices' => $indices === [] ? null : $indices,
            'criteria' => $this->enum($action['criteria'] ?? null, self::CRITERIA, null),
        ];
    }

    /**
     * @param  array<string, mixed>  $slots
     * @return array<string, mixed>
     */
    private function clarify(string $reason, ?string $service, float $confidence, array $slots = []): array
    {
        return $this->payload(
            intent: 'clarify',
            confidence: $confidence,
            service: $service,
            slots: $slots,
            actionType: 'none',
            targetIndex: null,
            targetIndices: null,
            criteria: null,
            clarifyReason: $reason,
        );
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<int>|null  $targetIndices
     * @return array<string, mixed>
     */
    private function payload(
        string $intent,
        float $confidence,
        ?string $service,
        array $slots,
        string $actionType,
        ?int $targetIndex,
        ?array $targetIndices,
        ?string $criteria,
        ?string $clarifyReason,
    ): array {
        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'service_type' => $service,
            'slots' => $slots === [] ? new \stdClass() : $slots,
            'action' => [
                'type' => $actionType,
                'target_index' => $targetIndex,
                'target_indices' => $targetIndices,
                'criteria' => $criteria,
            ],
            'clarify_reason' => $clarifyReason,
        ];
    }

    private function serviceType(mixed $value): ?string
    {
        return $this->enum($value, self::SERVICE_TYPES, null);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function enum(mixed $value, array $allowed, ?string $default): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function confidence(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return max(0.0, min(1.0, (float) $value));
    }
}
