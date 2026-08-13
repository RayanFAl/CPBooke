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
        $items = is_array($items) ? $items : [];
        $bookingFlightData = $this->resolveBookingFlightData($payload, $requestPayload, $details, $items);
        $isBundle = $this->isBundle($order, $details, $payload, $requestPayload, $items);
        $esims = $this->resolveEsimSummaries($order, $details, $items, $isBundle);
        $insurances = $this->resolveInsuranceSummaries($order, $details, $items, $isBundle);
        $hotels = $this->resolveHotelSummaries($order, $details, $items);
        $guests = $payload['guests']
            ?? $requestPayload['guests']
            ?? $payload['passengers']
            ?? $requestPayload['passengers']
            ?? $details['guests']
            ?? [];
        $providerBooking = $payload['provider_booking']
            ?? $requestPayload['provider_booking']
            ?? [
                'booking_id' => $order->external_booking_id,
                'provider' => $order->provider_name,
            ];

        $nonFlightTypes = [
            Order::SERVICE_TYPE_ESIM,
            Order::SERVICE_TYPE_INSURANCE,
            Order::SERVICE_TYPE_HOTEL,
        ];

        return [
            'cpbooke_id' => $order->id,
            'id' => $order->external_booking_id ?: (string) $order->id,
            'number' => $order->booking_reference,
            'product_type' => $order->service_type,
            'service_type' => $order->service_type,
            'is_bundle' => $isBundle,
            'external_booking_id' => $order->external_booking_id,
            'provider_booking' => is_array($providerBooking) ? $providerBooking : null,
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
            'guests' => is_array($guests) ? $guests : [],
            'passengers' => $payload['passengers'] ?? $requestPayload['passengers'] ?? [],
            'items' => $items,
            'payment' => $payload['payment'] ?? $requestPayload['payment'] ?? null,
            'booking_flight_data' => in_array($order->service_type, $nonFlightTypes, true)
                ? null
                : $bookingFlightData,
            'flight' => $this->resolveFlightSummary($order, $details, $items, $isBundle),
            'seats' => $this->resolveSeats($details, $items),
            'hotel' => $hotels[0] ?? null,
            'hotels' => $hotels,
            'user_review' => $order->service_type === Order::SERVICE_TYPE_HOTEL
                ? $this->resolveUserReview($order)
                : null,
            'esim' => $esims[0] ?? null,
            'esims' => $esims,
            'insurance' => $insurances[0] ?? null,
            'insurances' => $insurances,
            'metadata' => $payload['metadata'] ?? $requestPayload['metadata'] ?? null,
            'details' => in_array($order->service_type, $nonFlightTypes, true)
                ? $details
                : $this->resolveFlightDetails($details, $bookingFlightData, $items),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestPayload
     * @param  array<int, array<string, mixed>>  $items
     */
    private function isBundle(Order $order, array $details, array $payload, array $requestPayload, array $items): bool
    {
        if (($details['bundle'] ?? false) === true) {
            return true;
        }

        $metadata = $payload['metadata'] ?? $requestPayload['metadata'] ?? null;

        if (is_array($metadata) && ($metadata['bundle'] ?? false) === true) {
            return true;
        }

        if ($order->service_type !== Order::SERVICE_TYPE_FLIGHT) {
            return false;
        }

        $types = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = strtolower((string) ($item['type'] ?? ''));

            if ($type !== '') {
                $types[$type] = true;
            }
        }

        return isset($types['flight']) && (isset($types['esim']) || isset($types['insurance']) || isset($types['seat']));
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function resolveFlightSummary(Order $order, array $details, array $items, bool $isBundle): ?array
    {
        if ($order->service_type !== Order::SERVICE_TYPE_FLIGHT && ! $isBundle) {
            return null;
        }

        if (in_array($order->service_type, [
            Order::SERVICE_TYPE_ESIM,
            Order::SERVICE_TYPE_INSURANCE,
            Order::SERVICE_TYPE_HOTEL,
        ], true)) {
            return null;
        }

        $flightItem = $this->firstItemOfType($items, 'flight') ?? ($items[0] ?? []);
        $itemDetails = is_array($flightItem['item_details'] ?? null) ? $flightItem['item_details'] : [];

        return [
            'title' => $flightItem['title'] ?? null,
            'item_id' => isset($details['flight_item_id'])
                ? (string) $details['flight_item_id']
                : (isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null),
            'pnr' => $details['pnr'] ?? ($itemDetails['pnr'] ?? null),
            'seats' => $details['seats'] ?? ($itemDetails['seats'] ?? null),
            'origin' => $details['origin'] ?? null,
            'destination' => $details['destination'] ?? null,
            'departure_time' => $details['departure_time'] ?? null,
            'airline' => $details['airline'] ?? ($itemDetails['airline_name'] ?? null),
            'airline_code' => $details['airline_code'] ?? ($itemDetails['airline_code'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function resolveSeats(array $details, array $items): ?array
    {
        if (isset($details['seats']) && is_array($details['seats'])) {
            return $details['seats'];
        }

        $flightItem = $this->firstItemOfType($items, 'flight');
        $itemDetails = is_array($flightItem['item_details'] ?? null) ? $flightItem['item_details'] : [];

        return is_array($itemDetails['seats'] ?? null) ? $itemDetails['seats'] : null;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveHotelSummaries(Order $order, array $details, array $items): array
    {
        if ($order->service_type !== Order::SERVICE_TYPE_HOTEL) {
            return [];
        }

        $firstItem = $this->firstItemOfType($items, 'hotel') ?? ($items[0] ?? []);
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

        return [[
            'title' => $details['title'] ?? ($firstItem['title'] ?? null),
            'hotel_id' => isset($details['hotel_id'])
                ? (string) $details['hotel_id']
                : (isset($itemDetails['hotel_id']) ? (string) $itemDetails['hotel_id'] : null),
            'hotel_name' => $details['hotel_name'] ?? ($itemDetails['hotel_name'] ?? null),
            'city_id' => isset($details['city_id'])
                ? (string) $details['city_id']
                : (isset($itemDetails['city_id']) ? (string) $itemDetails['city_id'] : null),
            'city_name' => $details['city_name'] ?? ($itemDetails['city_name'] ?? null),
            'country' => $details['country'] ?? ($itemDetails['country'] ?? null),
            'source' => $details['source'] ?? ($itemDetails['source'] ?? null),
            'offer_id' => isset($details['offer_id'])
                ? (string) $details['offer_id']
                : (isset($itemDetails['offer_id']) ? (string) $itemDetails['offer_id'] : null),
            'room_name' => $details['room_name'] ?? ($itemDetails['room_name'] ?? null),
            'room_type' => $details['room_type'] ?? ($itemDetails['room_type'] ?? null),
            'board' => $details['board'] ?? ($itemDetails['board'] ?? null),
            'check_in' => $details['check_in'] ?? ($itemDetails['check_in'] ?? null),
            'check_out' => $details['check_out'] ?? ($itemDetails['check_out'] ?? null),
            'nights' => $details['nights'] ?? ($itemDetails['nights'] ?? null),
            'rooms' => $details['rooms'] ?? ($itemDetails['rooms'] ?? null),
            'adults' => $details['adults'] ?? ($itemDetails['adults'] ?? null),
            'children' => $details['children'] ?? ($itemDetails['children'] ?? null),
            'guests_count' => $details['guests_count'] ?? ($itemDetails['guests_count'] ?? null),
            'stars' => $details['stars'] ?? ($itemDetails['stars'] ?? null),
            'address' => $details['address'] ?? ($itemDetails['address'] ?? null),
            'image_url' => $details['image_url'] ?? ($itemDetails['image_url'] ?? null),
            'guests' => $details['guests'] ?? ($itemDetails['guests'] ?? []),
            'quantity' => $details['quantity'] ?? ($firstItem['quantity'] ?? 1),
        ]];
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveEsimSummaries(Order $order, array $details, array $items, bool $isBundle): array
    {
        if ($order->service_type === Order::SERVICE_TYPE_ESIM) {
            $firstItem = $items[0] ?? [];
            $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

            return [[
                'title' => $details['title'] ?? ($firstItem['title'] ?? null),
                'country' => $details['country'] ?? ($itemDetails['country'] ?? null),
                'data' => $details['data'] ?? ($itemDetails['data'] ?? null),
                'validity_days' => $details['validity_days'] ?? ($itemDetails['validity_days'] ?? null),
                'iccid' => $details['iccid'] ?? ($itemDetails['iccid'] ?? null),
                'activation_code' => $details['activation_code'] ?? ($itemDetails['activation_code'] ?? null),
                'qr' => $details['qr'] ?? ($itemDetails['qr'] ?? null),
                'quantity' => $details['quantity'] ?? ($firstItem['quantity'] ?? 1),
                'item_id' => isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null,
                'booking_uuid' => isset($itemDetails['booking_uuid']) ? (string) $itemDetails['booking_uuid'] : null,
            ]];
        }

        if (isset($details['esims']) && is_array($details['esims'])) {
            return array_values(array_filter($details['esims'], 'is_array'));
        }

        if (! $isBundle) {
            return [];
        }

        return array_map(
            function (array $item): array {
                $itemDetails = is_array($item['item_details'] ?? null) ? $item['item_details'] : [];

                return [
                    'title' => $item['title'] ?? null,
                    'country' => $itemDetails['country'] ?? null,
                    'data' => $itemDetails['data'] ?? null,
                    'validity_days' => $itemDetails['validity_days'] ?? null,
                    'iccid' => $itemDetails['iccid'] ?? null,
                    'activation_code' => $itemDetails['activation_code'] ?? null,
                    'qr' => $itemDetails['qr'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'item_id' => isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null,
                    'booking_uuid' => isset($itemDetails['booking_uuid']) ? (string) $itemDetails['booking_uuid'] : null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'total' => $item['total'] ?? null,
                    'currency' => $item['currency'] ?? null,
                ];
            },
            $this->itemsOfType($items, 'esim'),
        );
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveInsuranceSummaries(Order $order, array $details, array $items, bool $isBundle): array
    {
        if ($order->service_type === Order::SERVICE_TYPE_INSURANCE) {
            $firstItem = $items[0] ?? [];
            $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

            return [[
                'title' => $details['title'] ?? ($firstItem['title'] ?? null),
                'product_subtype' => $details['product_subtype'] ?? ($firstItem['product_subtype'] ?? null),
                'item_id' => isset($details['item_id'])
                    ? (string) $details['item_id']
                    : (isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null),
                'order_id' => isset($itemDetails['order_id']) ? (string) $itemDetails['order_id'] : null,
                'provider' => $details['provider'] ?? ($itemDetails['provider'] ?? null),
                'ticket_number' => $details['ticket_number'] ?? ($itemDetails['ticket_number'] ?? null),
                'report_reference' => $details['report_reference'] ?? ($itemDetails['report_reference'] ?? null),
                'zone_id' => $details['zone_id'] ?? ($itemDetails['zone_id'] ?? null),
                'zone_name' => $details['zone_name'] ?? ($itemDetails['zone_name'] ?? null),
                'duration_id' => $details['duration_id'] ?? ($itemDetails['duration_id'] ?? null),
                'duration_label' => $details['duration_label'] ?? ($itemDetails['duration_label'] ?? null),
                'policy_date_from' => $details['policy_date_from'] ?? ($itemDetails['policy_date_from'] ?? null),
                'policy_date_to' => $details['policy_date_to'] ?? ($itemDetails['policy_date_to'] ?? null),
                'quantity' => $details['quantity'] ?? ($firstItem['quantity'] ?? 1),
            ]];
        }

        if (isset($details['insurances']) && is_array($details['insurances'])) {
            return array_values(array_filter($details['insurances'], 'is_array'));
        }

        if (! $isBundle) {
            return [];
        }

        return array_map(
            function (array $item): array {
                $itemDetails = is_array($item['item_details'] ?? null) ? $item['item_details'] : [];

                return [
                    'title' => $item['title'] ?? null,
                    'product_subtype' => $item['product_subtype'] ?? null,
                    'item_id' => isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null,
                    'order_id' => isset($itemDetails['order_id']) ? (string) $itemDetails['order_id'] : null,
                    'provider' => $itemDetails['provider'] ?? null,
                    'ticket_number' => $itemDetails['ticket_number'] ?? null,
                    'report_reference' => $itemDetails['report_reference'] ?? null,
                    'zone_id' => $itemDetails['zone_id'] ?? null,
                    'zone_name' => $itemDetails['zone_name'] ?? null,
                    'duration_id' => $itemDetails['duration_id'] ?? null,
                    'duration_label' => $itemDetails['duration_label'] ?? null,
                    'policy_date_from' => $itemDetails['policy_date_from'] ?? null,
                    'policy_date_to' => $itemDetails['policy_date_to'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? null,
                    'total' => $item['total'] ?? null,
                    'currency' => $item['currency'] ?? null,
                ];
            },
            $this->itemsOfType($items, 'insurance'),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemsOfType(array $items, string $type): array
    {
        return array_values(array_filter(
            $items,
            static fn ($item): bool => is_array($item) && strtolower((string) ($item['type'] ?? '')) === $type,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function firstItemOfType(array $items, string $type): ?array
    {
        foreach ($items as $item) {
            if (is_array($item) && strtolower((string) ($item['type'] ?? '')) === $type) {
                return $item;
            }
        }

        return null;
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

        $flightItem = $this->firstItemOfType($items, 'flight') ?? ($items[0] ?? []);
        $itemDetails = is_array($flightItem['item_details'] ?? null) ? $flightItem['item_details'] : [];
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
            $flightItem = $this->firstItemOfType($items, 'flight') ?? ($items[0] ?? []);
            $itemDetails = is_array($flightItem['item_details'] ?? null) ? $flightItem['item_details'] : [];
            $segments = is_array($itemDetails['segments'] ?? null) ? $itemDetails['segments'] : [];

            if ($segments !== []) {
                $resolved['segments'] = $segments;
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveUserReview(Order $order): ?array
    {
        $review = $order->relationLoaded('hotelReview')
            ? $order->hotelReview
            : $order->hotelReview()->first();

        if ($review === null) {
            return null;
        }

        return HotelReviewResource::make($review)->resolve(request());
    }
}
