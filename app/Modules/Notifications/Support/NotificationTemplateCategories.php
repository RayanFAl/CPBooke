<?php

namespace App\Modules\Notifications\Support;

final class NotificationTemplateCategories
{
    public const ORDERS = 'orders';

    public const PAYMENTS = 'payments';

    public const FLIGHTS = 'flights';

    public const HOTELS = 'hotels';

    public const REMINDERS = 'reminders';

    public const INSURANCE = 'insurance';

    public const SECURITY = 'security';

    public const SUPPORT = 'support';

    public const LOYALTY = 'loyalty';

    public const OFFERS = 'offers';

    public const GENERAL = 'general';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ORDERS,
            self::PAYMENTS,
            self::FLIGHTS,
            self::HOTELS,
            self::REMINDERS,
            self::INSURANCE,
            self::SECURITY,
            self::SUPPORT,
            self::LOYALTY,
            self::OFFERS,
            self::GENERAL,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ORDERS => 'Orders & bookings',
            self::PAYMENTS => 'Payments & refunds',
            self::FLIGHTS => 'Flights',
            self::HOTELS => 'Hotels',
            self::REMINDERS => 'Reminders',
            self::INSURANCE => 'Insurance',
            self::SECURITY => 'Security',
            self::SUPPORT => 'Support',
            self::LOYALTY => 'Loyalty',
            self::OFFERS => 'Travel offers',
            self::GENERAL => 'General',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelsAr(): array
    {
        return [
            self::ORDERS => 'الحجوزات',
            self::PAYMENTS => 'الدفع والاسترداد',
            self::FLIGHTS => 'الرحلات',
            self::HOTELS => 'الفنادق',
            self::REMINDERS => 'التذكيرات',
            self::INSURANCE => 'التأمين',
            self::SECURITY => 'الأمان',
            self::SUPPORT => 'الدعم',
            self::LOYALTY => 'الولاء',
            self::OFFERS => 'عروض السفر',
            self::GENERAL => 'عام',
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, label_ar: string}>
     */
    public static function options(): array
    {
        $arabic = self::labelsAr();

        return collect(self::labels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'label_ar' => $arabic[$value] ?? $label,
            ])
            ->values()
            ->all();
    }
}
