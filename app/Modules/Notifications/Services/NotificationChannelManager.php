<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Channels\EmailNotificationChannel;
use App\Modules\Notifications\Channels\InAppNotificationChannel;
use App\Modules\Notifications\Channels\PushNotificationChannel;
use App\Modules\Notifications\Channels\SmsNotificationChannel;
use App\Modules\Notifications\Channels\WhatsAppNotificationChannel;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Settings\Services\SystemSettingsService;
use InvalidArgumentException;

class NotificationChannelManager
{
    public function __construct(
        private readonly FcmHttpV1Client $fcmHttpV1Client,
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

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
        $pushConfigured = $this->fcmHttpV1Client->isConfigured();
        $whatsappConfigured = filled(config('services.notifications.whatsapp_endpoint'))
            || ! app()->environment('production');

        return [
            [
                'channel' => NotificationChannels::IN_APP,
                'healthy' => true,
                'provider' => 'database',
                'configured' => true,
                'enabled' => true,
            ],
            [
                'channel' => NotificationChannels::EMAIL,
                'healthy' => true,
                'provider' => config('mail.default', 'mail'),
                'configured' => filled(config('mail.mailers.'.config('mail.default').'.transport'))
                    || config('mail.default') === 'log'
                    || config('mail.default') === 'array',
                'enabled' => $this->systemSettingsService->isChannelEnabled(NotificationChannels::EMAIL),
            ],
            [
                'channel' => NotificationChannels::PUSH,
                'healthy' => $pushConfigured,
                'provider' => 'fcm-http-v1',
                'configured' => $pushConfigured,
                'enabled' => $this->systemSettingsService->isChannelEnabled(NotificationChannels::PUSH),
            ],
            [
                'channel' => NotificationChannels::SMS,
                'healthy' => filled(config('services.notifications.sms_endpoint')),
                'provider' => 'sms-gateway',
                'configured' => filled(config('services.notifications.sms_endpoint')),
                'enabled' => $this->systemSettingsService->isChannelEnabled(NotificationChannels::SMS),
            ],
            [
                'channel' => NotificationChannels::WHATSAPP,
                'healthy' => $whatsappConfigured,
                'provider' => $whatsappConfigured && ! filled(config('services.notifications.whatsapp_endpoint'))
                    ? 'whatsapp-simulated'
                    : 'whatsapp-gateway',
                'configured' => $whatsappConfigured,
                'enabled' => $this->systemSettingsService->isChannelEnabled(NotificationChannels::WHATSAPP),
            ],
        ];
    }
}
