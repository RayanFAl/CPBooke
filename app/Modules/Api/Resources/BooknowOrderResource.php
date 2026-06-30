<?php

namespace App\Modules\Api\Resources;

use App\Models\Order;
use App\Support\Orders\BooknowOrderStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BooknowOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $payload = is_array($order->response_payload) ? $order->response_payload : [];
        $requestPayload = is_array($order->request_payload) ? $order->request_payload : [];

        $details = is_array($order->details) ? $order->details : [];
        $items = $payload['items'] ?? $requestPayload['items'] ?? [];
        $bookingFlightData = $this->resolveBookingFlightData($payload, $requestPayload, $details, $items);

        return [
            'cpbooke_id' => $order->id,
            'id' => $order->external_booking_id ?: (string) $order->id,
            'number' => $order->booking_reference,
            'provider_order_number' => $payload['provider_order_number']
                ?? $details['provider_order_number']
                ?? ($requestPayload['provider_booking']['order_number'] ?? null),
            'status' => $payload['status'] ?? BooknowOrderStatusMapper::toProvider($order->status),
            'internal_status' => $order->status,
            'grand_total' => number_format((float) $order->total_amount, 2, '.', ''),
            'base_amount' => $order->base_amount !== null
                ? number_format((float) $order->base_amount, 2, '.', '')
                : null,
            'tax_amount' => $order->tax_amount !== null
                ? number_format((float) $order->tax_amount, 2, '.', '')
                : null,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
            'contact' => $payload['contact'] ?? $requestPayload['contact'] ?? [],
            'passengers' => $payload['passengers'] ?? $requestPayload['passengers'] ?? [],
            'items' => $items,
            'payment' => $payload['payment'] ?? $requestPayload['payment'] ?? null,
            'booking_flight_data' => $bookingFlightData,
            'metadata' => $payload['metadata'] ?? $requestPayload['metadata'] ?? null,
            'details' => $this->resolveFlightDetails($details, $bookingFlightData, $items),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function resolveBookingFlightData(array $payload, array $requestPayload, array $details, array $items): ?array
    {
        $stored = $payload['booking_flight_data'] ?? $requestPayload['booking_flight_data'] ?? null;

        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        $firstItem = $items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];
        $segments = is_array($itemDetails['segments'] ?? null) ? $itemDetails['segments'] : [];

        if ($segments === []) {
            if (($details['origin'] ?? null) === null && ($details['destination'] ?? null) === null) {
                return null;
            }

            return [
                'departure_airport' => $details['origin'] ?? null,
                'arrival_airport' => $details['destination'] ?? null,
                'departure_time' => $details['departure_time'] ?? null,
                'segments' => [],
            ];
        }

        $firstSegment = is_array($segments[0] ?? null) ? $segments[0] : [];

        return [
            'departure_airport' => $firstSegment['departure_airport'] ?? $details['origin'] ?? null,
            'arrival_airport' => $firstSegment['arrival_airport'] ?? $details['destination'] ?? null,
            'departure_time' => $firstSegment['departure_time'] ?? $details['departure_time'] ?? null,
            'segments' => $segments,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>|null  $bookingFlightData
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function resolveFlightDetails(array $details, ?array $bookingFlightData, array $items): array
    {
        $resolved = $details;

        if (is_array($bookingFlightData)) {
            $resolved['origin'] = $bookingFlightData['departure_airport'] ?? $resolved['origin'] ?? null;
            $resolved['destination'] = $bookingFlightData['arrival_airport'] ?? $resolved['destination'] ?? null;
            $resolved['departure_time'] = $bookingFlightData['departure_time'] ?? $resolved['departure_time'] ?? null;

            if (! empty($bookingFlightData['segments']) && is_array($bookingFlightData['segments'])) {
                $resolved['segments'] = $bookingFlightData['segments'];
            }
        }

        if (! isset($resolved['segments'])) {
            $firstItem = $items[0] ?? [];
            $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];
            $segments = is_array($itemDetails['segments'] ?? null) ? $itemDetails['segments'] : [];

            if ($segments !== []) {
                $resolved['segments'] = $segments;
            }
        }

        return $resolved;
    }
}
