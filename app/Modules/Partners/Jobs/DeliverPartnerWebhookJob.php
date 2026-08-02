<?php

namespace App\Modules\Partners\Jobs;

use App\Models\PartnerWebhookDelivery;
use App\Modules\Partners\Services\PartnerWebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverPartnerWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $deliveryId,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(PartnerWebhookSigner $signer): void
    {
        $delivery = PartnerWebhookDelivery::query()
            ->with(['endpoint.partner'])
            ->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if ($delivery->status === PartnerWebhookDelivery::STATUS_SENT) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->is_active || $endpoint->partner === null || ! $endpoint->partner->isActive()) {
            $delivery->forceFill([
                'status' => PartnerWebhookDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'response_body' => 'Endpoint or partner inactive.',
            ])->save();

            return;
        }

        $rawBody = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = $signer->sign($timestamp, $rawBody, $endpoint->plainSigningSecret());
        $headers = $signer->headers($delivery->event, $delivery->id, $timestamp, $signature);

        $delivery->forceFill([
            'attempt_count' => max($delivery->attempt_count, $this->attempts()),
            'status' => PartnerWebhookDelivery::STATUS_PENDING,
        ])->save();

        $response = Http::timeout(10)
            ->withHeaders($headers)
            ->withBody($rawBody, 'application/json')
            ->post($endpoint->url);

        $body = substr((string) $response->body(), 0, 4000);

        if ($response->successful()) {
            $delivery->forceFill([
                'status' => PartnerWebhookDelivery::STATUS_SENT,
                'response_code' => $response->status(),
                'response_body' => $body,
                'delivered_at' => now(),
                'failed_at' => null,
            ])->save();

            return;
        }

        $delivery->forceFill([
            'response_code' => $response->status(),
            'response_body' => $body,
        ])->save();

        $response->throw();
    }

    public function failed(Throwable $exception): void
    {
        $delivery = PartnerWebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->forceFill([
            'status' => PartnerWebhookDelivery::STATUS_FAILED,
            'attempt_count' => max($delivery->attempt_count, $this->attempts()),
            'response_body' => substr($exception->getMessage(), 0, 4000),
            'failed_at' => now(),
        ])->save();
    }
}
