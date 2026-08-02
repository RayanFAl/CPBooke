<?php

namespace App\Modules\Partners\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerWebhookDelivery;
use App\Models\PartnerWebhookEndpoint;
use App\Modules\Partners\Jobs\DeliverPartnerWebhookJob;
use App\Modules\Partners\Support\PartnerWebhookEvents;

class PartnerWebhookDispatcher
{
    public function dispatchOrderEvent(string $event, Order $order, ?FinancialTransaction $transaction = null): void
    {
        if (! in_array($event, PartnerWebhookEvents::all(), true)) {
            return;
        }

        $payload = $this->buildPayload($event, $order, $transaction);

        PartnerWebhookEndpoint::query()
            ->where('is_active', true)
            ->whereHas('partner', fn ($query) => $query->where('status', Partner::STATUS_ACTIVE))
            ->orderBy('id')
            ->each(function (PartnerWebhookEndpoint $endpoint) use ($event, $payload): void {
                if (! $endpoint->listensFor($event)) {
                    return;
                }

                $delivery = PartnerWebhookDelivery::query()->create([
                    'partner_id' => $endpoint->partner_id,
                    'partner_webhook_endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'status' => PartnerWebhookDelivery::STATUS_PENDING,
                    'attempt_count' => 0,
                    'payload' => $payload,
                ]);

                DeliverPartnerWebhookJob::dispatch($delivery->id);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $event, Order $order, ?FinancialTransaction $transaction): array
    {
        $payload = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'external_booking_id' => $order->external_booking_id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'service_type' => $order->service_type,
                    'currency' => $order->currency,
                    'total_amount' => $order->total_amount,
                    'final_amount' => $order->final_amount,
                    'customer_id' => $order->customer_id,
                    'provider_id' => $order->provider_id,
                    'provider_name' => $order->provider_name,
                    'source' => $order->source,
                    'updated_at' => optional($order->updated_at)?->toIso8601String(),
                ],
            ],
        ];

        if ($transaction !== null) {
            $payload['data']['transaction'] = [
                'id' => $transaction->id,
                'type' => $transaction->type ?? null,
                'status' => $transaction->status ?? null,
                'amount' => $transaction->amount ?? null,
                'currency' => $transaction->currency ?? null,
            ];
        }

        return $payload;
    }
}
