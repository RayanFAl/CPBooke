<?php

namespace App\Modules\Notifications\Support;

final class NotificationTemplateSamples
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'user_name' => 'Mohammed Al-Ahmad',
            'order_id' => '1042',
            'order_reference' => 'CP-BK-2026-1042',
            'order_status' => 'confirmed',
            'service_type' => 'flight',
            'product_type' => 'flight',
            'amount' => '1,250.00',
            'currency' => 'SAR',
            'reason' => 'Card declined by bank',
            'summary' => 'Gate changed to B12.',
            'deep_link' => '/my-orders',
            'reminder_window' => '24h',
            'ticket_number' => 'SUP-9081',
            'ticket_subject' => 'Refund request for booking CP-BK-2026-1042',
            'from_tier' => 'Silver',
            'tier_name' => 'Gold',
            'device_name' => 'iPhone 15',
            'location' => 'Riyadh, SA',
            'ip' => '203.0.113.10',
            'hotel_name' => 'Marriott Riyadh',
            'pnr' => 'ABC123',
            'destination' => 'Tunis',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forCode(string $code): array
    {
        $samples = self::defaults();

        return match ($code) {
            'HOTEL_BOOKING_CONFIRMED', 'HOTEL_CHECKIN_REMINDER_24H', 'HOTEL_BOOKING_CANCELLED', 'HOTEL_BOOKING_MODIFIED', 'HOTEL_CHECKIN_CHANGED', 'HOTEL_CHECKOUT_CHANGED', 'HOTEL_CANCELLATION_DEADLINE_REMINDER' => array_merge($samples, [
                'service_type' => 'hotel',
                'product_type' => 'hotel',
                'deadline' => '23:59',
                'from_value' => '2026-09-20',
                'to_value' => '2026-09-21',
            ]),
            'PAYMENT_SUCCEEDED', 'PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'REFUND_ISSUED', 'REFUND_INITIATED', 'REFUND_COMPLETED', 'REFUND_FAILED' => array_merge($samples, [
                'service_type' => 'payment',
            ]),
            'INSURANCE_POLICY_ISSUED' => array_merge($samples, [
                'service_type' => 'insurance',
                'product_type' => 'insurance',
            ]),
            'FLIGHT_TICKET_ISSUED', 'FLIGHT_STATUS_UPDATED', 'FLIGHT_REMINDER_24H', 'FLIGHT_REMINDER_3H', 'FLIGHT_REMINDER_1H',
            'FLIGHT_TIME_CHANGED', 'FLIGHT_ARRIVAL_CHANGED', 'FLIGHT_CHANGED', 'FLIGHT_GATE_CHANGED', 'FLIGHT_TERMINAL_CHANGED',
            'FLIGHT_SEAT_CHANGED', 'FLIGHT_CLASS_CHANGED', 'FLIGHT_DELAYED', 'FLIGHT_CANCELLED' => array_merge($samples, [
                'service_type' => 'flight',
                'product_type' => 'flight',
                'from_value' => '15:30',
                'to_value' => '17:00',
                'route' => 'Tripoli → Tunis',
            ]),
            'OFFER_ESIM', 'OFFER_INSURANCE', 'OFFER_ESIM_FOR_TRIP', 'OFFER_INSURANCE_FOR_TRIP', 'OFFER_HOTELS_AT_DESTINATION', 'OFFER_CARS_AT_DESTINATION', 'OFFER_RETURN_FLIGHT', 'POST_TRIP_THANKS', 'POST_TRIP_NEXT', 'LOYALTY_NEAR_REWARD', 'DESTINATION_ARRIVAL', 'ABANDONED_FLIGHT_SEARCH', 'PRICE_ALERT_HIT' => array_merge($samples, [
                'destination' => 'Tunis',
                'origin' => 'Tripoli',
                'route' => 'Tripoli → Tunis',
                'departure_clock' => '15:30',
                'departure_date' => '20 Sep 2026',
                'destination_country' => 'TN',
                'price' => '1,250',
                'target_price' => '800',
                'checklist_hint' => 'Almost ready — still missing: eSIM, Hotel',
                'checklist_hint_ar' => 'كل شيء جاهز تقريباً! بقي فقط: eSIM، الفندق',
                'deep_link' => '/flights?origin=TIP&destination=TUN',
            ]),
            'PAYMENT_REMINDER' => array_merge($samples, [
                'deep_link' => '/my-orders',
            ]),
            'LOGIN_ALERT' => array_merge($samples, [
                'deep_link' => '/login',
            ]),
            default => $samples,
        };
    }

    public static function notificationType(string $code, ?string $category = null): string
    {
        return match ($code) {
            'PAYMENT_SUCCEEDED', 'PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'REFUND_ISSUED', 'REFUND_INITIATED', 'REFUND_COMPLETED', 'REFUND_FAILED' => 'payment',
            'FLIGHT_TICKET_ISSUED', 'FLIGHT_STATUS_UPDATED', 'FLIGHT_REMINDER_24H', 'FLIGHT_REMINDER_3H', 'FLIGHT_REMINDER_1H',
            'FLIGHT_TIME_CHANGED', 'FLIGHT_ARRIVAL_CHANGED', 'FLIGHT_CHANGED', 'FLIGHT_GATE_CHANGED', 'FLIGHT_TERMINAL_CHANGED',
            'FLIGHT_SEAT_CHANGED', 'FLIGHT_CLASS_CHANGED', 'FLIGHT_DELAYED', 'FLIGHT_CANCELLED' => 'flight',
            'ORDER_CREATED' => 'order',
            'PAYMENT_REMINDER' => 'payment',
            'OFFER_ESIM', 'OFFER_INSURANCE', 'OFFER_ESIM_FOR_TRIP', 'OFFER_INSURANCE_FOR_TRIP', 'OFFER_HOTELS_AT_DESTINATION', 'OFFER_CARS_AT_DESTINATION', 'OFFER_RETURN_FLIGHT', 'POST_TRIP_NEXT', 'ABANDONED_FLIGHT_SEARCH', 'PRICE_ALERT_HIT' => 'tag',
            'DESTINATION_ARRIVAL', 'POST_TRIP_THANKS', 'ORDER_CONFIRMED', 'HOTEL_BOOKING_CONFIRMED', 'HOTEL_CHECKIN_REMINDER_24H', 'INSURANCE_POLICY_ISSUED', 'ESIM_ORDER_CONFIRMED', 'LOYALTY_TIER_CHANGED', 'LOYALTY_NEAR_REWARD' => 'success',
            'BOOKING_CANCELLED', 'BOOKING_FAILED', 'HOTEL_BOOKING_CANCELLED', 'HOTEL_BOOKING_MODIFIED', 'HOTEL_CHECKIN_CHANGED', 'HOTEL_CHECKOUT_CHANGED' => 'order',
            default => match ($category) {
                NotificationTemplateCategories::PAYMENTS => 'payment',
                NotificationTemplateCategories::FLIGHTS, NotificationTemplateCategories::REMINDERS => 'flight',
                NotificationTemplateCategories::ORDERS => 'order',
                NotificationTemplateCategories::HOTELS, NotificationTemplateCategories::INSURANCE, NotificationTemplateCategories::LOYALTY => 'success',
                default => 'system',
            },
        };
    }
}
