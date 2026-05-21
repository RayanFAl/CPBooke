<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Support\Facades\Http;

class PushNotificationChannel implements NotificationChannel
{
    public function channel(): string
    {
        return NotificationChannels::PUSH;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function send(NotificationLog $log, NotificationTemplate $template, User $user, array $variables): array
    {
        $devices = $user->notificationDevices()
            ->where('channel', NotificationChannels::PUSH)
            ->where('is_active', true)
            ->pluck('device_token')
            ->all();

        if ($devices === []) {
            return [
                'provider' => 'fcm',
                'delivered' => false,
                'reason' => 'missing_device',
            ];
        }

        $payload = [
            'tokens' => $devices,
            'notification' => [
                'title' => $log->subject ?: config('app.name', 'Notification'),
                'body' => $log->body,
            ],
            'data' => [
                'related_type' => $log->related_type,
                'related_id' => (string) ($log->related_id ?? ''),
                'event_class' => $log->event_class,
            ],
        ];

        $endpoint = config('services.notifications.fcm_server_key') ? 'https://fcm.googleapis.com/fcm/send' : null;

        if ($endpoint !== null) {
            $response = Http::withToken((string) config('services.notifications.fcm_server_key'))
                ->post($endpoint, $payload)
                ->throw();

            return [
                'provider' => 'fcm',
                'delivered' => true,
                'tokens_count' => count($devices),
                'response' => $response->json(),
            ];
        }

        return [
            'provider' => 'push-simulated',
            'delivered' => true,
            'tokens_count' => count($devices),
            'payload' => $payload,
        ];
    }
}