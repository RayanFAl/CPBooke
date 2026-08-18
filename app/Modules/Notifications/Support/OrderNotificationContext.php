<?php

namespace App\Modules\Notifications\Support;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

    public static function departureTime(Order $order): ?CarbonInterface
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
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function arrivalTime(Order $order): ?CarbonInterface
    {
        $details = is_array($order->details) ? $order->details : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];
        $lastSegment = $segments !== [] ? end($segments) : [];

        $raw = $details['arrival_time']
            ?? data_get($details, 'outbound.arrival_time')
            ?? ($lastSegment['arrival_time'] ?? null)
            ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function destinationLabel(Order $order): string
    {
        $details = is_array($order->details) ? $order->details : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];
        $lastSegment = $segments !== [] ? end($segments) : [];

        $value = $details['destination_city']
            ?? $details['arrival_city']
            ?? $details['destination']
            ?? ($lastSegment['arrival_city'] ?? null)
            ?? ($lastSegment['arrival_airport'] ?? null)
            ?? $details['arrival_airport']
            ?? null;

        if (! is_string($value) || trim($value) === '') {
            return 'your destination';
        }

        return trim($value);
    }

    public static function originLabel(Order $order): string
    {
        $details = is_array($order->details) ? $order->details : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];
        $firstSegment = $segments[0] ?? [];

        $value = $details['origin_city']
            ?? $details['departure_city']
            ?? $details['origin']
            ?? ($firstSegment['departure_city'] ?? null)
            ?? ($firstSegment['departure_airport'] ?? null)
            ?? $details['departure_airport']
            ?? null;

        if (! is_string($value) || trim($value) === '') {
            return 'origin';
        }

        return trim($value);
    }

    public static function originAirport(Order $order): ?string
    {
        $details = is_array($order->details) ? $order->details : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];
        $firstSegment = $segments[0] ?? [];
        $value = $details['departure_airport']
            ?? ($firstSegment['departure_airport'] ?? null)
            ?? $details['origin']
            ?? null;

        return is_string($value) && trim($value) !== '' ? strtoupper(trim($value)) : null;
    }

    public static function destinationCountry(Order $order): ?string
    {
        $details = is_array($order->details) ? $order->details : [];
        $explicit = $details['destination_country'] ?? $details['country'] ?? $details['esim_country'] ?? null;

        if (is_string($explicit) && strlen(trim($explicit)) === 2) {
            return strtoupper(trim($explicit));
        }

        $code = self::destinationAirportCode($order) ?? self::destinationLabel($order);

        return self::countryFromPlace($code);
    }

    public static function destinationAirportCode(Order $order): ?string
    {
        $details = is_array($order->details) ? $order->details : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];
        $lastSegment = $segments !== [] ? end($segments) : [];
        $value = $details['arrival_airport']
            ?? ($lastSegment['arrival_airport'] ?? null)
            ?? $details['destination']
            ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $trimmed) === 1 ? $trimmed : null;
    }

    public static function destinationCitySlug(Order $order): string
    {
        $label = self::destinationLabel($order);
        $mapped = match (strtoupper($label)) {
            'TUN', 'TUNIS', 'تونس' => 'tunis',
            'TIP', 'TRIPOLI', 'طرابلس' => 'tripoli',
            'MJI', 'MITIGA', 'معيتيقة', 'معيتيقه' => 'tripoli',
            default => strtolower($label),
        };

        return preg_replace('/[^a-z0-9]+/', '-', $mapped) ?: 'destination';
    }

    public static function countryFromPlace(string $place): ?string
    {
        $value = strtoupper(trim($place));

        $airports = [
            'TUN' => 'TN', 'DJE' => 'TN', 'SFA' => 'TN', 'NBE' => 'TN', 'MIR' => 'TN',
            'MJI' => 'LY', 'TIP' => 'LY', 'MRA' => 'LY', 'BEN' => 'LY', 'LAQ' => 'LY',
            'IST' => 'TR', 'SAW' => 'TR', 'AYT' => 'TR',
            'CAI' => 'EG', 'HRG' => 'EG', 'SSH' => 'EG',
        ];

        if (isset($airports[$value])) {
            return $airports[$value];
        }

        if (str_contains($value, 'TUNIS') || str_contains($value, 'تونس') || $value === 'TN') {
            return 'TN';
        }

        if (str_contains($value, 'TRIPOLI') || str_contains($value, 'MITIGA') || str_contains($value, 'طرابلس') || str_contains($value, 'معيتيق')) {
            return 'LY';
        }

        return strlen($value) === 2 ? $value : null;
    }

    public static function formatClock(?CarbonInterface $time): ?string
    {
        return $time?->timezone(config('app.timezone'))->format('H:i');
    }

    /**
     * @return array<string, mixed>
     */
    public static function journeyPayload(Order $order, string $deepLink): array
    {
        $destination = self::destinationLabel($order);
        $origin = self::originLabel($order);
        $departure = self::departureTime($order);
        $arrival = self::arrivalTime($order);

        return array_merge(self::base($order), [
            'origin' => $origin,
            'destination' => $destination,
            'origin_airport' => self::originAirport($order),
            'destination_country' => self::destinationCountry($order),
            'route' => $origin.' → '.$destination,
            'departure_time' => $departure?->toIso8601String(),
            'departure_clock' => self::formatClock($departure),
            'arrival_time' => $arrival?->toIso8601String(),
            'arrival_clock' => self::formatClock($arrival),
            'deep_link' => $deepLink,
        ]);
    }

    public static function orderDeepLink(Order $order): string
    {
        return '/my-orders/'.$order->id;
    }

    public static function checkInDate(Order $order): ?CarbonInterface
    {
        $details = is_array($order->details) ? $order->details : [];
        $raw = $details['check_in'] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function checkOutDate(Order $order): ?CarbonInterface
    {
        $details = is_array($order->details) ? $order->details : [];
        $raw = $details['check_out'] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function cancellationDeadline(Order $order): ?CarbonInterface
    {
        $details = is_array($order->details) ? $order->details : [];
        $raw = $details['cancellation_deadline']
            ?? $details['free_cancellation_until']
            ?? $details['cancel_by']
            ?? data_get($details, 'item_details.cancellation_deadline')
            ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
