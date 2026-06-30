<?php

namespace App\Modules\Admin\Orders\Services;

use App\Models\Order;

class OrderTicketPayloadBuilder
{
    /**
     * Build a structured ticket snapshot from stored order payloads.
     *
     * @return array<string, mixed>
     */
    public function build(Order $order, bool $canViewFinancials = false): array
    {
        $details = is_array($order->details) ? $order->details : [];
        $responsePayload = is_array($order->response_payload) ? $order->response_payload : [];
        $requestPayload = is_array($order->request_payload) ? $order->request_payload : [];

        $passengers = $responsePayload['passengers'] ?? $requestPayload['passengers'] ?? [];
        $items = $responsePayload['items'] ?? $requestPayload['items'] ?? [];
        $contact = $responsePayload['contact'] ?? $requestPayload['contact'] ?? $details['contact'] ?? [];
        $payment = $responsePayload['payment'] ?? $requestPayload['payment'] ?? $details['payment'] ?? [];
        $metadata = $responsePayload['metadata'] ?? $requestPayload['metadata'] ?? $details['metadata'] ?? null;
        $bookingFlightData = $responsePayload['booking_flight_data']
            ?? $requestPayload['booking_flight_data']
            ?? $details['booking_flight_data']
            ?? null;

        $firstItem = $items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];

        if ($segments === []) {
            $segments = is_array($itemDetails['segments'] ?? null) ? $itemDetails['segments'] : [];
        }

        if ($segments === [] && is_array($bookingFlightData)) {
            $segments = is_array($bookingFlightData['segments'] ?? null) ? $bookingFlightData['segments'] : [];
        }

        $segments = $this->mergeSegmentMetadata(
            is_array($segments) ? $segments : [],
            is_array($itemDetails['segments'] ?? null) ? $itemDetails['segments'] : [],
            is_array($bookingFlightData['segments'] ?? null) ? $bookingFlightData['segments'] : [],
        );

        $firstSegment = is_array($segments[0] ?? null) ? $segments[0] : [];
        $hasSegments = $segments !== [];
        $passengers = $this->mergePassengerRecords(
            is_array($passengers) ? $passengers : [],
            is_array($itemDetails['passengers'] ?? null) ? $itemDetails['passengers'] : [],
        );
        $pnr = $details['pnr'] ?? ($itemDetails['pnr'] ?? ($firstItem['provider_reference'] ?? null));

        if (count($items) === 1) {
            $onlyItem = $items[0];
            $isDuplicateFlightItem = ($onlyItem['type'] ?? null) === 'flight'
                && ($onlyItem['provider_reference'] ?? null) === $pnr
                && $hasSegments;

            if ($isDuplicateFlightItem) {
                $items = [];
            }
        }

        $coveredDetailKeys = [
            'provider_status',
            'pnr',
            'airline',
            'airline_code',
            'passenger_name',
            'origin',
            'destination',
            'departure_time',
            'product_subtype',
            'contact',
            'payment',
            'metadata',
            'provider_order_number',
            'booking_flight_data',
            'segments',
        ];

        $extra = [];

        foreach ($details as $key => $value) {
            if (in_array($key, $coveredDetailKeys, true) || $value === null || $value === '') {
                continue;
            }

            $extra[$key] = $value;
        }

        $providerOrderNumber = $details['provider_order_number']
            ?? ($responsePayload['provider_order_number'] ?? null)
            ?? ($requestPayload['provider_booking']['order_number'] ?? null);

        return [
            'service_type' => $order->service_type,
            'pnr' => $pnr,
            'provider_order_number' => $providerOrderNumber,
            'ticket_number' => $providerOrderNumber,
            'provider_status' => $details['provider_status'] ?? ($responsePayload['status'] ?? null),
            'airline' => $details['airline'] ?? ($itemDetails['airline_name'] ?? null),
            'airline_code' => $details['airline_code'] ?? ($itemDetails['airline_code'] ?? null),
            'origin' => $hasSegments ? null : (
                $details['origin']
                ?? ($firstSegment['departure_airport'] ?? null)
                ?? (is_array($bookingFlightData) ? ($bookingFlightData['departure_airport'] ?? null) : null)
            ),
            'destination' => $hasSegments ? null : (
                $details['destination']
                ?? ($firstSegment['arrival_airport'] ?? null)
                ?? (is_array($bookingFlightData) ? ($bookingFlightData['arrival_airport'] ?? null) : null)
            ),
            'departure_time' => $hasSegments ? null : (
                $details['departure_time']
                ?? ($firstSegment['departure_time'] ?? null)
                ?? (is_array($bookingFlightData) ? ($bookingFlightData['departure_time'] ?? null) : null)
            ),
            'product_subtype' => $details['product_subtype'] ?? ($firstItem['product_subtype'] ?? null),
            'passengers' => $passengers,
            'segments' => $segments,
            'segment_coupons' => $this->buildSegmentCouponRows(
                is_array($segments) ? $segments : [],
                $passengers,
                is_array($items) ? $items : [],
            ),
            'contact' => is_array($contact) ? $contact : [],
            'payment' => is_array($payment) ? $payment : [],
            'metadata' => is_array($metadata) ? $metadata : null,
            'items' => is_array($items) ? $items : [],
            'extra' => $extra,
            'order_context' => [
                'provider_name' => $order->provider_name,
                'service_type' => $order->service_type,
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'total_amount' => $canViewFinancials ? $order->total_amount : null,
                'base_amount' => $canViewFinancials ? $order->base_amount : null,
                'tax_amount' => $canViewFinancials ? $order->tax_amount : null,
                'currency' => $canViewFinancials ? $order->currency : null,
                'customer_phone' => $order->customer?->phone,
                'payment_status' => $order->payment_status,
            ],
        ];
    }

    /**
     * Build compact coupon rows for the admin ticket view.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<int, array<string, mixed>>  $passengers
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildSegmentCouponRows(array $segments, array $passengers, array $items): array
    {
        if ($segments === []) {
            return [];
        }

        $firstItem = $items[0] ?? [];
        $itemTotal = $firstItem['total'] ?? null;
        $itemCurrency = $firstItem['currency'] ?? null;
        $passengerList = $passengers !== [] ? $passengers : [[]];
        $rows = [];

        foreach ($segments as $segmentIndex => $segment) {
            if (! is_array($segment)) {
                continue;
            }

            foreach ($passengerList as $passengerIndex => $passenger) {
                if (! is_array($passenger)) {
                    $passenger = [];
                }

                $couponNumber = $segment['coupon']
                    ?? $segment['coupon_number']
                    ?? sprintf('%02d', $segmentIndex + 1);

                $rows[] = [
                    'segment_index' => $segmentIndex,
                    'flight_number' => $segment['flight_number'] ?? null,
                    'departure_airport' => $segment['departure_airport'] ?? null,
                    'arrival_airport' => $segment['arrival_airport'] ?? null,
                    'departure_time' => $segment['departure_time'] ?? null,
                    'arrival_time' => $segment['arrival_time'] ?? null,
                    'etkt' => $this->resolveSegmentEtkt($segment, $passenger, $segmentIndex, $passengerIndex),
                    'status_code' => $this->resolveSegmentStatusCode($segment, $passenger, $segmentIndex, $passengerIndex),
                    'cabin_type' => $segment['cabin_type'] ?? null,
                    'booking_class' => $segment['class'] ?? $segment['booking_class'] ?? null,
                    'coupon' => is_string($couponNumber) || is_numeric($couponNumber) ? (string) $couponNumber : null,
                    'passenger_name' => $this->formatPassengerName($passenger),
                    'price' => $segment['price'] ?? $segment['fare'] ?? $itemTotal,
                    'currency' => $segment['currency'] ?? $itemCurrency,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<int, array<string, mixed>>  ...$fallbackLists
     * @return array<int, array<string, mixed>>
     */
    private function mergeSegmentMetadata(array $segments, array ...$fallbackLists): array
    {
        $merged = $segments;

        foreach ($fallbackLists as $fallbackList) {
            foreach ($fallbackList as $index => $fallback) {
                if (! is_array($fallback)) {
                    continue;
                }

                if (! isset($merged[$index])) {
                    $merged[$index] = $fallback;

                    continue;
                }

                foreach ([
                    'cabin_type',
                    'class',
                    'booking_class',
                    'action_code',
                    'segment_status',
                    'status',
                    'etkt',
                    'e_ticket',
                    'ticket_number',
                    'electronic_ticket',
                    'coupon',
                    'coupon_number',
                ] as $key) {
                    $current = $merged[$index][$key] ?? null;

                    if (($current === null || $current === '') && isset($fallback[$key]) && $fallback[$key] !== '') {
                        $merged[$index][$key] = $fallback[$key];
                    }
                }

                if (($merged[$index]['booking_class'] ?? null) === null && isset($fallback['class']) && $fallback['class'] !== '') {
                    $merged[$index]['booking_class'] = $fallback['class'];
                }
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, array<string, mixed>>  $passengers
     * @param  array<int, array<string, mixed>>  ...$fallbackLists
     * @return array<int, array<string, mixed>>
     */
    private function mergePassengerRecords(array $passengers, array ...$fallbackLists): array
    {
        $merged = $passengers;

        if ($merged === []) {
            foreach ($fallbackLists as $fallbackList) {
                if ($fallbackList !== []) {
                    $merged = $fallbackList;

                    break;
                }
            }
        }

        foreach ($fallbackLists as $fallbackList) {
            foreach ($fallbackList as $index => $fallback) {
                if (! is_array($fallback)) {
                    continue;
                }

                if (! isset($merged[$index])) {
                    $merged[$index] = $fallback;

                    continue;
                }

                foreach (['tickets', 'etkt', 'e_ticket', 'ticket_number', 'electronic_ticket'] as $key) {
                    $current = $merged[$index][$key] ?? null;

                    if (($current === null || $current === '') && isset($fallback[$key]) && $fallback[$key] !== '') {
                        $merged[$index][$key] = $fallback[$key];
                    }
                }
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  array<string, mixed>  $passenger
     */
    private function resolveSegmentStatusCode(array $segment, array $passenger, int $segmentIndex, int $passengerIndex): ?string
    {
        foreach (['action_code', 'segment_status', 'status'] as $key) {
            $value = $segment[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $tickets = is_array($passenger['tickets'] ?? null) ? $passenger['tickets'] : [];

        foreach ($tickets as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            $coupons = is_array($ticket['coupons'] ?? null) ? $ticket['coupons'] : [];

            foreach ($coupons as $couponIndex => $coupon) {
                if (! is_array($coupon)) {
                    continue;
                }

                $couponSegmentIndex = $coupon['segment_index'] ?? $couponIndex;
                $couponPassengerIndex = $coupon['passenger_index'] ?? $passengerIndex;

                if ((int) $couponSegmentIndex !== $segmentIndex || (int) $couponPassengerIndex !== $passengerIndex) {
                    continue;
                }

                foreach (['action_code', 'status', 'segment_status'] as $key) {
                    $value = $coupon[$key] ?? null;

                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $passenger
     */
    private function formatPassengerName(array $passenger): ?string
    {
        $title = isset($passenger['title']) && $passenger['title'] !== ''
            ? strtoupper(trim((string) $passenger['title'])).' '
            : '';
        $name = trim(($passenger['first_name'] ?? '').' '.($passenger['last_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return strtoupper(trim($title.$name));
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  array<string, mixed>  $passenger
     */
    private function resolveSegmentEtkt(array $segment, array $passenger, int $segmentIndex, int $passengerIndex): ?string
    {
        foreach (['etkt', 'e_ticket', 'ticket_number', 'electronic_ticket'] as $key) {
            $value = $segment[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach (['etkt', 'e_ticket', 'ticket_number', 'electronic_ticket'] as $key) {
            $value = $passenger[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $tickets = is_array($passenger['tickets'] ?? null) ? $passenger['tickets'] : [];

        foreach ($tickets as $ticketIndex => $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            $number = $ticket['number'] ?? $ticket['ticket_number'] ?? $ticket['etkt'] ?? null;
            $coupons = is_array($ticket['coupons'] ?? null) ? $ticket['coupons'] : [];

            if ($coupons !== []) {
                foreach ($coupons as $couponIndex => $coupon) {
                    if (! is_array($coupon)) {
                        continue;
                    }

                    $couponSegmentIndex = $coupon['segment_index'] ?? $couponIndex;
                    $couponPassengerIndex = $coupon['passenger_index'] ?? $passengerIndex;

                    if ((int) $couponSegmentIndex !== $segmentIndex || (int) $couponPassengerIndex !== $passengerIndex) {
                        continue;
                    }

                    $couponNumber = $coupon['number'] ?? $coupon['etkt'] ?? $number;

                    if (! is_string($couponNumber) || $couponNumber === '') {
                        continue;
                    }

                    $couponSuffix = $coupon['coupon'] ?? $coupon['coupon_number'] ?? null;

                    if ($couponSuffix !== null && $couponSuffix !== '') {
                        return str_contains($couponNumber, '/')
                            ? $couponNumber
                            : $couponNumber.'/'.ltrim((string) $couponSuffix, '/');
                    }

                    return $couponNumber;
                }
            }

            if (is_string($number) && $number !== '' && ($ticketIndex === $passengerIndex || count($tickets) === 1)) {
                $couponSuffix = $segment['coupon'] ?? $segment['coupon_number'] ?? sprintf('%02d', $segmentIndex + 1);

                return str_contains($number, '/')
                    ? $number
                    : $number.'/'.ltrim((string) $couponSuffix, '/');
            }
        }

        return null;
    }

    public function resolveTicketNumber(Order $order): ?string
    {
        $ticket = $this->build($order, false);
        $ticketNumber = $ticket['provider_order_number'] ?? null;

        return is_string($ticketNumber) && $ticketNumber !== '' ? $ticketNumber : null;
    }
}
