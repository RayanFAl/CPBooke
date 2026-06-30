<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Support\Facades\Http;

class WhatsAppNotificationChannel implements NotificationChannel
{
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

        return [
            'provider' => 'whatsapp-simulated',
            'delivered' => true,
            'recipient' => $user->phone,
            'payload' => $payload,
        ];
    }
}