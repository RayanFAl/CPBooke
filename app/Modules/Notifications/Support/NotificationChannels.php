<?php

namespace App\Modules\Notifications\Support;

class NotificationChannels
{
    public const IN_APP = 'in_app';

    public const EMAIL = 'email';

    public const PUSH = 'push';

    public const SMS = 'sms';

    public const WHATSAPP = 'whatsapp';

    /**
     * Get all supported notification channels.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::IN_APP,
            self::EMAIL,
            self::PUSH,
            self::SMS,
            self::WHATSAPP,
        ];
    }
}