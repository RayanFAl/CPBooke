<?php

namespace App\Modules\Notifications\Support;

final class NotificationTopics
{
    public const FLIGHT_UPDATES = 'flight_updates';

    public const BOOKING_REMINDERS = 'booking_reminders';

    public const PROMOTIONAL = 'promotional';

    public const INSURANCE = 'insurance';

    public const HOTEL = 'hotel';

    public const CAR_RENTAL = 'car_rental';

    public const LOGIN_ALERTS = 'login_alerts';

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            self::FLIGHT_UPDATES => true,
            self::BOOKING_REMINDERS => true,
            self::PROMOTIONAL => false,
            self::INSURANCE => true,
            self::HOTEL => true,
            self::CAR_RENTAL => false,
            self::LOGIN_ALERTS => true,
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }
}
