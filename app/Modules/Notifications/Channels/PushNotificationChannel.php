<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Services\FcmHttpV1Client;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly FcmHttpV1Client $fcmHttpV1Client,
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

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
        if (! $this->systemSettingsService->isChannelEnabled(NotificationChannels::PUSH)) {
            return [
                'provider' => 'fcm',
                'delivered' => false,
                'reason' => 'channel_disabled',
            ];
        }

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

        $title = $log->subject ?: $this->systemSettingsService->companyName();
        $body = (string) $log->body;

        $data = array_filter([
            'related_type' => $log->related_type,
            'related_id' => (string) ($log->related_id ?? ''),
            'event_class' => $log->event_class,
            'type' => isset($variables['notification_type'])
                ? (string) $variables['notification_type']
                : (string) ($log->notification_type ?? 'system'),
            'order_id' => $log->related_type === 'order' && $log->related_id
                ? (string) $log->related_id
                : (isset($variables['order_id']) ? (string) $variables['order_id'] : null),
            'deep_link' => isset($variables['deep_link']) ? (string) $variables['deep_link'] : (
                $log->related_type === 'order' && $log->related_id
                    ? '/my-orders'
                    : null
            ),
            'notification_id' => isset($variables['notification_id']) ? (string) $variables['notification_id'] : null,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        // FCM requires data values to be strings.
        $data = array_map(static fn (mixed $value): string => (string) $value, $data);

        if (! $this->fcmHttpV1Client->isConfigured()) {
            if (app()->environment('production')) {
                return [
                    'provider' => 'fcm',
                    'delivered' => false,
                    'reason' => 'channel_not_configured',
                    'tokens_count' => count($devices),
                ];
            }

            return [
                'provider' => 'push-simulated',
                'delivered' => true,
                'tokens_count' => count($devices),
                'payload' => [
                    'tokens' => $devices,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => $data,
                ],
                'note' => 'Firebase credentials missing. No real device notification was sent.',
            ];
        }

        $results = [];
        $success = 0;
        $failure = 0;

        foreach ($devices as $deviceToken) {
            try {
                $result = $this->fcmHttpV1Client->sendToToken($deviceToken, $title, $body, $data);
            } catch (Throwable $exception) {
                Log::warning('FCM send exception', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);

                $result = [
                    'delivered' => false,
                    'reason' => 'exception',
                    'message' => $exception->getMessage(),
                ];
            }

            $results[] = [
                'token_suffix' => substr($deviceToken, -8),
                'result' => $result,
            ];

            if (($result['delivered'] ?? false) === true) {
                $success++;
            } else {
                $failure++;
            }
        }

        return [
            'provider' => 'fcm-http-v1',
            'delivered' => $success > 0,
            'tokens_count' => count($devices),
            'success' => $success,
            'failure' => $failure,
            'results' => $results,
        ];
    }
}
