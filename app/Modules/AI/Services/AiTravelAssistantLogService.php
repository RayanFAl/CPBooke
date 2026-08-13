<?php

namespace App\Modules\AI\Services;

use App\Models\AiTravelAssistantLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AiTravelAssistantLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Request $request,
        string $mode,
        array $payload,
        int $latencyMs,
        bool $success = true,
        ?string $errorMessage = null,
    ): void {
        if (! (bool) config('ai.log_requests', true)) {
            return;
        }

        $message = $this->truncate($payload['message'] ?? null, 500);
        $slots = is_array($payload['slots'] ?? null) ? $payload['slots'] : [];
        if ($slots instanceof \stdClass) {
            $slots = (array) $slots;
        }

        try {
            AiTravelAssistantLog::query()->create([
                'user_id' => $request->user()?->id,
                'mode' => $mode,
                'message' => $message,
                'intent' => $this->stringOrNull($payload['intent'] ?? null, 64),
                'product' => $this->stringOrNull($payload['product'] ?? null, 32),
                'source' => $this->stringOrNull($payload['source'] ?? null, 32),
                'fallback' => (bool) ($payload['fallback'] ?? false),
                'fallback_reason' => $this->stringOrNull($payload['fallbackReason'] ?? $payload['fallback_reason'] ?? null, 64),
                'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
                'needs_clarification' => (bool) ($payload['needsClarification'] ?? $payload['needs_clarification'] ?? false),
                'missing_slots' => $this->arrayOrNull($payload['missingSlots'] ?? $payload['missing_slots'] ?? null),
                'slots_summary' => $this->summarizeSlots($slots),
                'recommendations_count' => $this->countRecommendations($payload),
                'offers_count' => isset($payload['offers_count']) ? (int) $payload['offers_count'] : null,
                'model' => $this->stringOrNull($payload['model'] ?? app(AiSettingsService::class)->model(), 64),
                'latency_ms' => max(0, $latencyMs),
                'success' => $success,
                'error_message' => $this->truncate($errorMessage, 500),
                'ip_address' => $request->ip(),
                'user_agent' => $this->truncate($request->userAgent(), 255),
            ]);
        } catch (\Throwable) {
            // Never break the travel-assistant API if logging fails (missing table, etc.).
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{logs: \Illuminate\Contracts\Pagination\LengthAwarePaginator, filters: array<string, mixed>, sources: list<string>, modes: list<string>}
     */
    public function listForAdmin(array $filters): array
    {
        $query = AiTravelAssistantLog::query()
            ->with(['user:id,name,email'])
            ->latest('id');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('message', 'like', '%'.$search.'%')
                    ->orWhere('intent', 'like', '%'.$search.'%')
                    ->orWhere('fallback_reason', 'like', '%'.$search.'%');
            });
        }

        if ($source = trim((string) ($filters['source'] ?? ''))) {
            $query->where('source', $source);
        }

        if ($mode = trim((string) ($filters['mode'] ?? ''))) {
            $query->where('mode', $mode);
        }

        if ($intent = trim((string) ($filters['intent'] ?? ''))) {
            $query->where('intent', $intent);
        }

        if (($filters['fallback'] ?? '') !== '') {
            $query->where('fallback', filter_var($filters['fallback'], FILTER_VALIDATE_BOOL));
        }

        if (($filters['success'] ?? '') !== '') {
            $query->where('success', filter_var($filters['success'], FILTER_VALIDATE_BOOL));
        }

        $logs = $query->paginate(25)->withQueryString();

        return [
            'logs' => $logs,
            'filters' => [
                'search' => $search ?? '',
                'source' => $source ?? '',
                'mode' => $mode ?? '',
                'intent' => $intent ?? '',
                'fallback' => (string) ($filters['fallback'] ?? ''),
                'success' => (string) ($filters['success'] ?? ''),
            ],
            'sources' => ['gemini', 'rules_hint', 'fallback', 'gemini_or_local'],
            'modes' => ['interpret', 'recommend', 'rules_hint'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function countRecommendations(array $payload): ?int
    {
        $items = $payload['recommendations'] ?? null;

        return is_array($items) ? count($items) : null;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @return array<string, mixed>|null
     */
    private function summarizeSlots(array $slots): ?array
    {
        if ($slots === []) {
            return null;
        }

        return Arr::only($slots, [
            'origin',
            'destination',
            'departureDate',
            'returnDate',
            'adults',
            'children',
            'infants',
            'cabinClass',
            'nonStop',
            'airline',
            'sortPreference',
            'hotelCity',
            'checkIn',
            'checkOut',
            'rooms',
            'zoneName',
            'insuranceStart',
            'insuranceEnd',
            'esimCountry',
        ]);
    }

    /**
     * @param  mixed  $value
     * @return list<string>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_map(static fn ($item) => (string) $item, $value));
    }

    private function stringOrNull(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $this->truncate($text, $max);
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim($value);

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
}
