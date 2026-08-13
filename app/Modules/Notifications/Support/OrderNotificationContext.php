<?php

namespace App\Modules\Notifications\Support;

use App\Models\Order;

final class OrderNotificationContext
{
    /**
     * @return array{
     *     service_type: string|null,
     *     product_type: string|null,
     *     topic: string|null,
     *     deep_link: string,
     *     user_name: string,
     *     order_id: int,
     *     order_reference: string,
     *     order_status: string
     * }
     */
    public static function base(Order $order): array
    {
        $serviceType = is_string($order->service_type) ? strtolower($order->service_type) : null;

        return [
            'service_type' => $serviceType,
            'product_type' => $serviceType,
            'topic' => self::topicForServiceType($serviceType),
            'deep_link' => '/my-orders',
            'user_name' => $order->customer?->full_name ?: $order->customer?->name ?: 'Customer',
            'order_id' => (int) $order->id,
            'order_reference' => $order->booking_reference ?: ('#'.$order->id),
            'order_status' => (string) $order->status,
        ];
    }

    public static function topicForServiceType(?string $serviceType): ?string
    {
        return match ($serviceType) {
            Order::SERVICE_TYPE_FLIGHT => null, // transactional flight events are not gated by flight_updates
            Order::SERVICE_TYPE_HOTEL => NotificationTopics::HOTEL,
            Order::SERVICE_TYPE_INSURANCE => NotificationTopics::INSURANCE,
            'car', 'car_rental', 'transfer' => NotificationTopics::CAR_RENTAL,
            default => null,
        };
    }

    public static function inboxTypeForConfirmed(Order $order): string
    {
        if ($order->status === Order::STATUS_TICKETED) {
            return 'success';
        }

        return match ($order->service_type) {
            Order::SERVICE_TYPE_HOTEL, Order::SERVICE_TYPE_INSURANCE, Order::SERVICE_TYPE_ESIM => 'success',
            Order::SERVICE_TYPE_FLIGHT => 'flight',
            default => 'order',
        };
    }

    public static function departureTime(Order $order): ?\Carbon\CarbonInterface
    {
        $details = is_array($order->details) ? $order->details : [];
        $raw = $details['departure_time']
            ?? data_get($details, 'outbound.departure_time')
            ?? data_get($details, 'segments.0.departure_time')
            ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function checkInDate(Order $order): ?\Carbon\CarbonInterface
    {
        $details = is_array($order->details) ? $order->details : [];
        $raw = $details['check_in'] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
