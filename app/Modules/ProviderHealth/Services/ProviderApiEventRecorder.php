<?php

namespace App\Modules\ProviderHealth\Services;

use App\Models\Provider;
use App\Models\ProviderApiEvent;
use Throwable;

class ProviderApiEventRecorder
{
    public function recordSyncSuccess(Provider $provider, int $latencyMs, ?int $orderId = null): ProviderApiEvent
    {
        return $this->record(
            provider: $provider,
            eventType: ProviderApiEvent::TYPE_SYNC_SUCCESS,
            success: true,
            latencyMs: $latencyMs,
            referenceType: $orderId ? 'order' : null,
            referenceId: $orderId ? (string) $orderId : null,
            message: 'Order sync completed',
        );
    }

    public function recordSyncFailure(Provider $provider, int $latencyMs, Throwable $exception): ProviderApiEvent
    {
        return $this->record(
            provider: $provider,
            eventType: ProviderApiEvent::TYPE_SYNC_FAILURE,
            success: false,
            latencyMs: $latencyMs,
            message: mb_substr($exception->getMessage(), 0, 240),
            metadata: ['exception' => $exception::class],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Provider $provider,
        string $eventType,
        bool $success,
        ?int $latencyMs = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $message = null,
        array $metadata = [],
    ): ProviderApiEvent {
        return ProviderApiEvent::query()->create([
            'provider_id' => $provider->id,
            'event_type' => $eventType,
            'latency_ms' => $latencyMs,
            'success' => $success,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'message' => $message,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
