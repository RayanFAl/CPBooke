<?php

namespace App\Modules\Providers\Services;

use App\Models\Provider;
use App\Models\ProviderApiLog;

class ProviderApiLogService
{
    /**
     * @param  array<string, mixed>|null  $requestBody
     * @param  array<string, mixed>|null  $responseBody
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        Provider $provider,
        string $endpointKey,
        ?int $statusCode,
        bool $success,
        ?int $responseTimeMs,
        ?array $requestBody = null,
        ?array $responseBody = null,
        ?string $errorMessage = null,
        ?string $correlationId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $context = null,
    ): ProviderApiLog {
        $meta = $this->endpointMeta($endpointKey);

        return ProviderApiLog::query()->create([
            'provider_id' => $provider->id,
            'correlation_id' => $correlationId,
            'service' => $meta['service'],
            'endpoint_key' => $endpointKey,
            'endpoint_label' => $meta['label'],
            'endpoint_path' => $meta['path'],
            'http_method' => $meta['method'],
            'status_code' => $statusCode,
            'success' => $success,
            'response_time_ms' => $responseTimeMs,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'request_body' => $requestBody,
            'response_body' => $responseBody,
            'context' => $context,
            'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 500) : null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array{service: string, method: string, path: string, label: string}
     */
    private function endpointMeta(string $endpointKey): array
    {
        $catalog = config('provider_api.endpoint_catalog', []);
        $fallback = [
            'service' => 'unknown',
            'method' => 'POST',
            'path' => '/unknown',
            'label' => $endpointKey,
        ];

        $entry = $catalog[$endpointKey] ?? null;
        if (! is_array($entry)) {
            return $fallback;
        }

        return [
            'service' => (string) ($entry['service'] ?? 'unknown'),
            'method' => (string) ($entry['method'] ?? 'POST'),
            'path' => (string) ($entry['path'] ?? '/unknown'),
            'label' => (string) ($entry['label'] ?? $endpointKey),
        ];
    }
}
