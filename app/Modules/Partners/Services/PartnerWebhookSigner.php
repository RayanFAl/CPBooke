<?php

namespace App\Modules\Partners\Services;

class PartnerWebhookSigner
{
    public function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $event, int $deliveryId, string $timestamp, string $signature): array
    {
        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'CPBooke-Webhooks/1.0',
            'X-CPBooke-Event' => $event,
            'X-CPBooke-Delivery' => (string) $deliveryId,
            'X-CPBooke-Timestamp' => $timestamp,
            'X-CPBooke-Signature' => 'sha256='.$signature,
        ];
    }
}
