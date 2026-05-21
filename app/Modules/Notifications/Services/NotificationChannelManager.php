<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Channels\EmailNotificationChannel;
use App\Modules\Notifications\Channels\InAppNotificationChannel;
use App\Modules\Notifications\Channels\PushNotificationChannel;
use App\Modules\Notifications\Channels\SmsNotificationChannel;
use App\Modules\Notifications\Channels\WhatsAppNotificationChannel;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;
use InvalidArgumentException;

class NotificationChannelManager
{
    /**
     * Resolve the channel driver implementation.
     */
    public function driver(string $channel): NotificationChannel
    {
        return match ($channel) {
            NotificationChannels::IN_APP => app(InAppNotificationChannel::class),
            NotificationChannels::EMAIL => app(EmailNotificationChannel::class),
            NotificationChannels::PUSH => app(PushNotificationChannel::class),
            NotificationChannels::SMS => app(SmsNotificationChannel::class),
            NotificationChannels::WHATSAPP => app(WhatsAppNotificationChannel::class),
            default => throw new InvalidArgumentException('Unsupported notification channel ['.$channel.'].'),
        };
    }

    /**
     * Build a lightweight status overview for admin monitoring.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statuses(): array
    {
        return [
            [
                'channel' => NotificationChannels::IN_APP,
                'healthy' => true,
                'provider' => 'database',
                'configured' => true,
            ],
            [
                'channel' => NotificationChannels::EMAIL,
                'healthy' => true,
                'provider' => config('mail.default', 'mail'),
                'configured' => true,
            ],
            [
                'channel' => NotificationChannels::PUSH,
                'healthy' => true,
                'provider' => 'fcm',
                'configured' => filled(config('services.notifications.fcm_server_key')),
            ],
            [
                'channel' => NotificationChannels::SMS,
                'healthy' => true,
                'provider' => 'sms-gateway',
                'configured' => filled(config('services.notifications.sms_endpoint')),
            ],
            [
                'channel' => NotificationChannels::WHATSAPP,
                'healthy' => true,
                'provider' => 'whatsapp-gateway',
                'configured' => filled(config('services.notifications.whatsapp_endpoint')),
            ],
        ];
    }
}