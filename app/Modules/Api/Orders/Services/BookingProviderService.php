<?php

namespace App\Modules\Api\Orders\Services;

use App\Models\Order;
use RuntimeException;
use Illuminate\Support\Str;

class BookingProviderService
{
    /**
     * Send the order request to the mock booking provider.
     *
     * @return array<string, mixed>
     */
    public function createBooking(Order $order): array
    {
        $payload = $order->request_payload ?? [];
        $providerName = Str::lower($order->provider_name);

        $shouldFail = ($payload['simulate_failure'] ?? false) === true
            || Str::contains($providerName, 'fail');

        if ($shouldFail) {
            throw new RuntimeException('The booking provider rejected the booking request.');
        }

        return [
            'provider' => $order->provider_name,
            'external_booking_id' => 'EXT-'.Str::upper(Str::random(10)),
            'booking_reference' => $order->booking_reference,
            'accepted_at' => now()->toIso8601String(),
            'status' => Order::STATUS_CONFIRMED,
        ];
    }
}