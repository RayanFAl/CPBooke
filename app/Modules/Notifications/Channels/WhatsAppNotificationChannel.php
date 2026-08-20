<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\WhatsAppSandboxInbox;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Support\Facades\Http;

class WhatsAppNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
        private readonly WhatsAppSandboxInbox $sandboxInbox,
    ) {
    }

    public function channel(): string
    {
        return NotificationChannels::WHATSAPP;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function send(NotificationLog $log, NotificationTemplate $template, User $user, array $variables): array
    {
        if (! $this->systemSettingsService->isChannelEnabled(NotificationChannels::WHATSAPP)) {
            return [
                'provider' => 'whatsapp-gateway',
                'delivered' => false,
                'reason' => 'channel_disabled',
            ];
        }

        if (! is_string($user->phone) || trim($user->phone) === '') {
            return [
                'provider' => 'whatsapp-gateway',
                'delivered' => false,
                'reason' => 'missing_phone',
            ];
        }

        $payload = [
            'to' => $user->phone,
            'body' => $log->body,
            'template_code' => $template->code,
            'sender' => $this->systemSettingsService->current()->whatsapp_sender_name,
        ];

        $endpoint = config('services.notifications.whatsapp_endpoint');

        if (is_string($endpoint) && trim($endpoint) !== '') {
            $response = Http::withToken((string) config('services.notifications.whatsapp_token'))
                ->post($endpoint, $payload)
                ->throw();

            return [
                'provider' => 'whatsapp-gateway',
                'delivered' => true,
                'recipient' => $user->phone,
                'response' => $response->json(),
            ];
        }

        if (app()->environment('production')) {
            return [
                'provider' => 'whatsapp-gateway',
                'delivered' => false,
                'reason' => 'channel_not_configured',
            ];
        }

        $this->sandboxInbox->record([
            'to' => $user->phone,
            'body' => $log->body,
            'subject' => $log->subject,
            'template_code' => $template->code,
            'sender' => $payload['sender'],
            'user_id' => $user->id,
            'recorded_at' => now()->toIso8601String(),
        ]);

        return [
            'provider' => 'whatsapp-simulated',
            'delivered' => true,
            'recipient' => $user->phone,
            'payload' => $payload,
        ];
    }
}