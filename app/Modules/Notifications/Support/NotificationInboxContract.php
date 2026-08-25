<?php

namespace App\Modules\Notifications\Support;

final class NotificationInboxContract
{
    public const FAMILY_TRANSACTIONAL = 'transactional';

    public const FAMILY_OPERATIONAL = 'operational';

    public const FAMILY_JOURNEY = 'journey';

    public const FAMILY_MARKETING = 'marketing';

    public const CATEGORY_FLIGHTS = 'flights';

    public const CATEGORY_HOTELS = 'hotels';

    public const CATEGORY_PAYMENTS = 'payments';

    public const CATEGORY_INSURANCE = 'insurance';

    public const CATEGORY_ESIM = 'esim';

    public const CATEGORY_OFFERS = 'offers';

    public const CATEGORY_SECURITY = 'security';

    public const CATEGORY_WALLET = 'wallet';

    public const CATEGORY_DOCUMENTS = 'documents';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function enrich(string $code, array $payload = []): array
    {
        $orderId = $payload['order_id'] ?? null;
        $orderLink = is_string($payload['deep_link'] ?? null) && $payload['deep_link'] !== ''
            ? $payload['deep_link']
            : ($orderId ? '/my-orders/'.$orderId : '/my-orders');

        return [
            'family' => self::family($code),
            'category' => self::category($code, $payload),
            'severity' => self::severity($code),
            'recipient' => 'passenger',
            'channels' => $payload['channels'] ?? ['push', 'in_app'],
            'actions' => self::actions($code, $payload, $orderLink),
            'expires_at' => now()->addHours(self::expiryHours($code))->toIso8601String(),
            'action_engine' => true,
        ];
    }

    public static function family(string $code): string
    {
        return match (true) {
            str_starts_with($code, 'OFFER_'),
            in_array($code, ['ABANDONED_FLIGHT_SEARCH', 'PRICE_ALERT_HIT', 'POST_TRIP_NEXT', 'LOYALTY_NEAR_REWARD', 'POST_TRIP_THANKS', 'SEAT_UPGRADE_AVAILABLE', 'HOTEL_ROOM_UPGRADE_AVAILABLE'], true) => self::FAMILY_MARKETING,
            in_array($code, [
                'FLIGHT_REMINDER_24H', 'FLIGHT_REMINDER_3H', 'FLIGHT_REMINDER_1H',
                'DESTINATION_ARRIVAL', 'HOTEL_CHECKIN_REMINDER_24H', 'HOTEL_CANCELLATION_DEADLINE_REMINDER',
                'HOTEL_CHECKOUT_REMINDER', 'ONLINE_CHECKIN_OPEN', 'CHECKIN_REMINDER_24H', 'CHECKIN_REMINDER_3H',
                'PASSPORT_EXPIRY_REMINDER', 'VISA_REMINDER', 'ESIM_ACTIVATION_REMINDER', 'BAGGAGE_REMINDER',
            ], true) => self::FAMILY_JOURNEY,
            str_starts_with($code, 'FLIGHT_') && $code !== 'FLIGHT_TICKET_ISSUED',
            str_starts_with($code, 'GATE_'),
            str_starts_with($code, 'BOARDING_'),
            str_starts_with($code, 'SEAT_') && ! str_contains($code, 'UPGRADE'),
            str_starts_with($code, 'BAGGAGE_') && $code !== 'BAGGAGE_REMINDER',
            in_array($code, [
                'HOTEL_BOOKING_MODIFIED', 'HOTEL_CHECKIN_CHANGED', 'HOTEL_CHECKOUT_CHANGED',
                'HOTEL_BOOKING_CANCELLED', 'BOOKING_CANCELLED', 'DOCUMENT_REQUIRED',
                'DOCUMENT_VERIFICATION_REQUIRED', 'VISA_DOCUMENT_MISSING',
            ], true) => self::FAMILY_OPERATIONAL,
            default => self::FAMILY_TRANSACTIONAL,
        };
    }

    public static function category(string $code, array $payload = []): string
    {
        $service = strtolower((string) ($payload['service_type'] ?? $payload['product_type'] ?? ''));

        if ($code === '' || in_array($code, ['ORDER_CREATED', 'ORDER_CONFIRMED', 'BOOKING_CANCELLED', 'BOOKING_FAILED'], true)) {
            return match ($service) {
                'flight' => self::CATEGORY_FLIGHTS,
                'hotel' => self::CATEGORY_HOTELS,
                'insurance' => self::CATEGORY_INSURANCE,
                'esim' => self::CATEGORY_ESIM,
                default => self::CATEGORY_PAYMENTS,
            };
        }

        return match (true) {
            str_starts_with($code, 'FLIGHT_'),
            str_starts_with($code, 'GATE_'),
            str_starts_with($code, 'BOARDING_'),
            str_starts_with($code, 'SEAT_'),
            str_starts_with($code, 'BAGGAGE_'),
            str_starts_with($code, 'CHECKIN_'),
            in_array($code, ['DESTINATION_ARRIVAL', 'ABANDONED_FLIGHT_SEARCH', 'PRICE_ALERT_HIT', 'ONLINE_CHECKIN_OPEN'], true) => self::CATEGORY_FLIGHTS,
            str_starts_with($code, 'HOTEL_') => self::CATEGORY_HOTELS,
            str_starts_with($code, 'WALLET_') => self::CATEGORY_WALLET,
            str_starts_with($code, 'PASSPORT_'),
            str_starts_with($code, 'DOCUMENT_'),
            str_starts_with($code, 'VISA_') => self::CATEGORY_DOCUMENTS,
            str_starts_with($code, 'PAYMENT_'),
            str_starts_with($code, 'REFUND_'),
            str_starts_with($code, 'LINK_') => self::CATEGORY_PAYMENTS,
            str_starts_with($code, 'INSURANCE_'),
            $code === 'OFFER_INSURANCE' || $code === 'OFFER_INSURANCE_FOR_TRIP' => self::CATEGORY_INSURANCE,
            str_starts_with($code, 'ESIM_'),
            $code === 'OFFER_ESIM' || $code === 'OFFER_ESIM_FOR_TRIP' => self::CATEGORY_ESIM,
            in_array($code, ['LOGIN_ALERT', 'NEW_DEVICE_LOGIN', 'PASSWORD_CHANGED', 'EMAIL_CHANGED', 'PHONE_CHANGED', 'ACCOUNT_SECURITY_ALERT'], true) => self::CATEGORY_SECURITY,
            str_starts_with($code, 'OFFER_'),
            str_starts_with($code, 'POINTS_'),
            in_array($code, ['POST_TRIP_NEXT', 'LOYALTY_NEAR_REWARD', 'POST_TRIP_THANKS', 'LOYALTY_TIER_CHANGED', 'REWARD_AVAILABLE', 'TIER_UPGRADED'], true) => self::CATEGORY_OFFERS,
            default => match (true) {
                str_contains(strtolower($code), 'hotel') => self::CATEGORY_HOTELS,
                default => self::CATEGORY_PAYMENTS,
            },
        };
    }

    /**
     * @return list<string>
     */
    public static function codesForCategory(string $category): array
    {
        return collect(array_keys(self::actionMap()))
            ->merge([
                'ORDER_CREATED', 'ORDER_CONFIRMED', 'FLIGHT_TICKET_ISSUED', 'HOTEL_BOOKING_CONFIRMED',
                'INSURANCE_POLICY_ISSUED', 'ESIM_ORDER_CONFIRMED', 'PAYMENT_SUCCEEDED', 'PAYMENT_FAILED',
                'PAYMENT_REMINDER', 'PAYMENT_EXPIRED', 'REFUND_ISSUED', 'REFUND_INITIATED', 'REFUND_COMPLETED',
                'REFUND_FAILED', 'FLIGHT_STATUS_UPDATED', 'FLIGHT_REMINDER_24H', 'FLIGHT_REMINDER_3H',
                'FLIGHT_REMINDER_1H', 'DESTINATION_ARRIVAL', 'LOGIN_ALERT', 'BOOKING_FAILED', 'BOOKING_CANCELLED',
            ])
            ->merge(array_column(NotificationActionCatalog::templates(), 'code'))
            ->unique()
            ->filter(fn (string $code): bool => self::category($code) === $category)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_FLIGHTS,
            self::CATEGORY_HOTELS,
            self::CATEGORY_PAYMENTS,
            self::CATEGORY_INSURANCE,
            self::CATEGORY_ESIM,
            self::CATEGORY_OFFERS,
            self::CATEGORY_SECURITY,
            self::CATEGORY_WALLET,
            self::CATEGORY_DOCUMENTS,
        ];
    }

    public static function severity(string $code): string
    {
        return match (true) {
            in_array($code, [
                'FLIGHT_CANCELLED', 'BOOKING_CANCELLED', 'HOTEL_BOOKING_CANCELLED',
                'PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'REFUND_FAILED', 'BOOKING_FAILED',
                'BOARDING_STARTED', 'BOARDING_FINAL_CALL', 'BOARDING_CLOSED',
                'GATE_ASSIGNED', 'GATE_CHANGED', 'FLIGHT_GATE_CHANGED',
                'ACCOUNT_SECURITY_ALERT', 'HOTEL_BOOKING_FAILED', 'SEAT_UPGRADE_FAILED',
            ], true) => 'critical',
            in_array($code, [
                'FLIGHT_DELAYED', 'FLIGHT_TIME_CHANGED', 'FLIGHT_ARRIVAL_CHANGED', 'FLIGHT_CHANGED',
                'FLIGHT_GATE_CHANGED', 'FLIGHT_TERMINAL_CHANGED', 'FLIGHT_SEAT_CHANGED', 'FLIGHT_CLASS_CHANGED',
                'HOTEL_BOOKING_MODIFIED', 'HOTEL_CHECKIN_CHANGED', 'HOTEL_CHECKOUT_CHANGED',
                'HOTEL_CANCELLATION_DEADLINE_REMINDER', 'PAYMENT_REMINDER',
                'PASSPORT_EXPIRY_REMINDER', 'DOCUMENT_REQUIRED', 'VISA_DOCUMENT_MISSING',
                'ESIM_LOW_DATA', 'WALLET_LOW_BALANCE', 'PAYMENT_REQUIRES_ACTION', 'SEAT_CHANGED',
            ], true) => 'warning',
            default => 'info',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{code: string, label: string, label_ar: string, deep_link: string}>
     */
    public static function actions(string $code, array $payload, string $orderLink): array
    {
        $support = '/support'.(isset($payload['order_id']) ? '?order_id='.$payload['order_id'] : '');
        $alternatives = $payload['alternatives_deep_link'] ?? $payload['deep_link'] ?? '/flights';
        $refund = $orderLink;

        return match ($code) {
            'FLIGHT_CANCELLED' => [
                self::action('view_alternatives', 'View alternative flights', 'عرض الرحلات البديلة', is_string($alternatives) ? $alternatives : '/flights'),
                self::action('request_refund', 'Request refund', 'طلب استرداد', $refund),
                self::action('contact_support', 'Contact support', 'التواصل مع الدعم', $support),
            ],
            'FLIGHT_TIME_CHANGED', 'FLIGHT_ARRIVAL_CHANGED', 'FLIGHT_CHANGED', 'FLIGHT_DELAYED',
            'FLIGHT_GATE_CHANGED', 'FLIGHT_TERMINAL_CHANGED', 'FLIGHT_SEAT_CHANGED', 'FLIGHT_CLASS_CHANGED',
            'FLIGHT_STATUS_UPDATED', 'GATE_ASSIGNED', 'GATE_CHANGED', 'SEAT_ASSIGNED', 'SEAT_CHANGED',
            'BOARDING_STARTED', 'BOARDING_FINAL_CALL', 'BOARDING_CLOSED', 'BOARDING_PASS_AVAILABLE' => [
                self::action('view_flight', 'View flight details', 'عرض تفاصيل الرحلة', $orderLink),
                self::action('contact_support', 'Contact support', 'التواصل مع الدعم', $support),
            ],
            'ONLINE_CHECKIN_OPEN', 'CHECKIN_REMINDER_24H', 'CHECKIN_REMINDER_3H',
            'FLIGHT_REMINDER_24H', 'FLIGHT_REMINDER_3H' => [
                self::action('check_in', 'Check-in', 'Check-in', $orderLink.'/check-in'),
                self::action('view_flight', 'View flight details', 'عرض تفاصيل الرحلة', $orderLink),
            ],
            'PASSPORT_EXPIRY_REMINDER', 'DOCUMENT_REQUIRED', 'DOCUMENT_VERIFICATION_REQUIRED',
            'VISA_REMINDER', 'VISA_DOCUMENT_MISSING' => [
                self::action('upload_document', 'Add document', 'إضافة المستند', '/profile/passengers'),
                self::action('view_order', 'View booking', 'عرض الحجز', $orderLink),
            ],
            'BAGGAGE_REMINDER', 'BAGGAGE_ALLOWANCE_UPDATED', 'BAGGAGE_PRICE_CHANGED' => [
                self::action('add_baggage', 'Add extra bag', 'إضافة حقيبة', $orderLink.'/baggage'),
            ],
            'SEAT_UPGRADE_AVAILABLE' => [
                self::action('view_seats', 'View seats', 'عرض المقاعد', $orderLink.'/seats'),
            ],
            'ESIM_ACTIVATION_REMINDER', 'ESIM_READY', 'ESIM_LOW_DATA', 'ESIM_EXPIRED' => [
                self::action('open_esim', 'Open eSIM', 'فتح eSIM', $payload['deep_link'] ?? '/esim'),
            ],
            'WALLET_TOPUP_SUCCESS', 'WALLET_DEBIT', 'WALLET_REFUND', 'WALLET_LOW_BALANCE' => [
                self::action('open_wallet', 'Open wallet', 'فتح المحفظة', '/wallet'),
            ],
            'PAYMENT_PENDING', 'PAYMENT_REQUIRES_ACTION' => [
                self::action('complete_payment', 'Complete payment', 'إتمام الدفع', $orderLink),
            ],
            'BOOKING_CANCELLED', 'HOTEL_BOOKING_CANCELLED' => [
                self::action('view_order', 'View booking', 'عرض الحجز', $orderLink),
                self::action('request_refund', 'Request refund', 'طلب استرداد', $refund),
                self::action('contact_support', 'Contact support', 'التواصل مع الدعم', $support),
            ],
            'REFUND_COMPLETED', 'REFUND_ISSUED' => [
                self::action('view_refund', 'View refund details', 'عرض تفاصيل الاسترداد', $orderLink),
            ],
            'REFUND_FAILED', 'PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'BOOKING_FAILED' => [
                self::action('retry_payment', 'Try again', 'إعادة المحاولة', $orderLink),
                self::action('contact_support', 'Contact support', 'التواصل مع الدعم', $support),
            ],
            'REFUND_INITIATED' => [
                self::action('view_refund', 'View refund status', 'عرض حالة الاسترداد', $orderLink),
            ],
            'ORDER_CREATED', 'PAYMENT_REMINDER' => [
                self::action('complete_payment', 'Complete payment', 'إتمام الدفع', $orderLink),
            ],
            'HOTEL_CANCELLATION_DEADLINE_REMINDER' => [
                self::action('view_hotel', 'View hotel booking', 'عرض حجز الفندق', $orderLink),
                self::action('cancel_booking', 'Cancel booking', 'إلغاء الحجز', $orderLink),
            ],
            'HOTEL_CHECKOUT_REMINDER' => [
                self::action('view_hotel', 'View hotel booking', 'عرض حجز الفندق', $orderLink),
            ],
            default => [
                self::action('open', 'Open', 'فتح', $orderLink),
            ],
        };
    }

    public static function expiryHours(string $code): int
    {
        return match (self::severity($code)) {
            'critical' => 6,
            'warning' => 24,
            default => 72,
        };
    }

    /**
     * @return array{code: string, label: string, label_ar: string, deep_link: string}
     */
    private static function action(string $code, string $label, string $labelAr, string $deepLink): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'label_ar' => $labelAr,
            'deep_link' => $deepLink,
        ];
    }

    /**
     * @return array<string, true>
     */
    private static function actionMap(): array
    {
        return [
            'FLIGHT_TIME_CHANGED' => true,
            'FLIGHT_ARRIVAL_CHANGED' => true,
            'FLIGHT_CHANGED' => true,
            'FLIGHT_GATE_CHANGED' => true,
            'FLIGHT_TERMINAL_CHANGED' => true,
            'FLIGHT_SEAT_CHANGED' => true,
            'FLIGHT_CLASS_CHANGED' => true,
            'FLIGHT_DELAYED' => true,
            'FLIGHT_CANCELLED' => true,
            'HOTEL_BOOKING_CANCELLED' => true,
            'HOTEL_BOOKING_MODIFIED' => true,
            'HOTEL_CHECKIN_CHANGED' => true,
            'HOTEL_CHECKOUT_CHANGED' => true,
            'HOTEL_CANCELLATION_DEADLINE_REMINDER' => true,
            'BOOKING_CANCELLED' => true,
            'BOOKING_FAILED' => true,
            'PAYMENT_EXPIRED' => true,
            'REFUND_INITIATED' => true,
            'REFUND_COMPLETED' => true,
            'REFUND_FAILED' => true,
        ];
    }
}
