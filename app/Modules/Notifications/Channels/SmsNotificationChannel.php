<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Support\Facades\Http;

class SmsNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

    public function channel(): string
    {
        return NotificationChannels::SMS;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function send(NotificationLog $log, NotificationTemplate $template, User $user, array $variables): array
    {
        if (! $this->systemSettingsService->isChannelEnabled(NotificationChannels::SMS)) {
            return [
                'provider' => 'sms-gateway',
                'delivered' => false,
                'reason' => 'channel_disabled',
            ];
        }

        if (! is_string($user->phone) || trim($user->phone) === '') {
            return [
                'provider' => 'sms-gateway',
                'delivered' => false,
                'reason' => 'missing_phone',
            ];
        }

        $payload = [
            'to' => $user->phone,
            'message' => $log->body,
            'template_code' => $template->code,
            'sender' => $this->systemSettingsService->current()->sms_sender_name,
        ];

        $endpoint = config('services.notifications.sms_endpoint');

        if (is_string($endpoint) && trim($endpoint) !== '') {
            $response = Http::withToken((string) config('services.notifications.sms_token'))
                ->post($endpoint, $payload)
                ->throw();

            return [
                'provider' => 'sms-gateway',
                'delivered' => true,
                'recipient' => $user->phone,
                'response' => $response->json(),
            ];
        }

        if (app()->environment('production')) {
            return [
                'provider' => 'sms-gateway',
                'delivered' => false,
                'reason' => 'channel_not_configured',
            ];
        }

        return [
            'provider' => 'sms-simulated',
            'delivered' => true,
            'recipient' => $user->phone,
            'payload' => $payload,
        ];
    }
}