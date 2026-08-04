<?php

namespace App\Modules\Providers\Services;

use App\Models\Provider;
use App\Models\ProviderApiLog;

class ProviderApiMonitoringService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function endpointMetrics(Provider $provider, array $filters = []): array
    {
        $catalog = config('provider_api.endpoint_catalog', []);

        return collect($catalog)
            ->map(function (array $meta, string $endpointKey) use ($provider, $filters): array {
                $query = $this->buildFilteredLogsQuery($provider, array_merge($filters, [
                    'endpoint' => $endpointKey,
                ]));

                $requests = (int) (clone $query)->count();
                $successes = (int) (clone $query)->where('success', true)->count();
                $failures = max($requests - $successes, 0);
                $successRate = $requests > 0 ? round(($successes / $requests) * 100, 2) : 0.0;
                $errorRate = $requests > 0 ? round(($failures / $requests) * 100, 2) : 0.0;
                $averageLatency = (clone $query)->whereNotNull('response_time_ms')->avg('response_time_ms');
                $lastFailure = (clone $query)
                    ->where('success', false)
                    ->latest('occurred_at')
                    ->first();

                return [
                    'endpoint_key' => $endpointKey,
                    'service' => (string) ($meta['service'] ?? 'unknown'),
                    'label' => (string) ($meta['label'] ?? $endpointKey),
                    'method' => (string) ($meta['method'] ?? 'POST'),
                    'path' => (string) ($meta['path'] ?? '/'),
                    'requests' => $requests,
                    'success_rate' => $successRate,
                    'error_rate' => $errorRate,
                    'average_latency_ms' => $averageLatency !== null ? (int) round((float) $averageLatency) : null,
                    'last_failure' => $lastFailure ? [
                        'status_code' => $lastFailure->status_code,
                        'message' => $lastFailure->error_message,
                        'occurred_at' => optional($lastFailure->occurred_at)->toIso8601String(),
                    ] : null,
                ];
            })
            ->filter(fn (array $item): bool => $item['requests'] > 0)
            ->sortByDesc('requests')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function recentLogs(Provider $provider, int $limit = 50, array $filters = []): array
    {
        return $this->buildFilteredLogsQuery($provider, $filters)
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProviderApiLog $log): array => [
                'id' => $log->id,
                'service' => $log->service,
                'endpoint_key' => $log->endpoint_key,
                'correlation_id' => $log->correlation_id,
                'endpoint_label' => $log->endpoint_label,
                'endpoint_path' => $log->endpoint_path,
                'http_method' => $log->http_method,
                'status_code' => $log->status_code,
                'success' => $log->success,
                'response_time_ms' => $log->response_time_ms,
                'reference_type' => $log->reference_type,
                'reference_id' => $log->reference_id,
                'error_message' => $log->error_message,
                'request_body' => $log->request_body,
                'response_body' => $log->response_body,
                'context' => $log->context,
                'occurred_at' => optional($log->occurred_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildFilteredLogsQuery(Provider $provider, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return ProviderApiLog::query()
            ->where('provider_id', $provider->id)
            ->when(filled($filters['service'] ?? null), fn ($query) => $query->where('service', (string) $filters['service']))
            ->when(filled($filters['endpoint'] ?? null), fn ($query) => $query->where('endpoint_key', (string) $filters['endpoint']))
            ->when(isset($filters['success']) && $filters['success'] !== '', function ($query) use ($filters): void {
                $value = $filters['success'];
                if ($value === 'success') {
                    $query->where('success', true);
                } elseif ($value === 'failed') {
                    $query->where('success', false);
                }
            })
            ->when(filled($filters['status_code'] ?? null), fn ($query) => $query->where('status_code', (int) $filters['status_code']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->where('occurred_at', '>=', (string) $filters['date_from'].' 00:00:00'))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->where('occurred_at', '<=', (string) $filters['date_to'].' 23:59:59'));
    }
}
