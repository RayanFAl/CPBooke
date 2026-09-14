<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotificationDevice;
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
    ) {}

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

        $devicesQuery = $user->notificationDevices()
            ->where('channel', NotificationChannels::PUSH)
            ->where('is_active', true);

        $latestVersionCode = isset($variables['version_code']) && is_numeric($variables['version_code'])
            ? (int) $variables['version_code']
            : null;

        if ($template->code === 'APP_UPDATE_AVAILABLE' && $latestVersionCode !== null) {
            $devicesQuery->where(function ($query) use ($latestVersionCode): void {
                $query->whereNull('app_version_code')
                    ->orWhere('app_version_code', '<', $latestVersionCode);
            });
        }

        $devices = $devicesQuery->pluck('device_token')->all();

        if ($devices === []) {
            return [
                'provider' => 'fcm',
                'delivered' => false,
                'reason' => $template->code === 'APP_UPDATE_AVAILABLE'
                    ? 'device_already_up_to_date'
                    : 'missing_device',
            ];
        }

        $title = $log->subject ?: $this->systemSettingsService->companyName();
        $body = (string) $log->body;

        $productType = isset($variables['product_type'])
            ? (string) $variables['product_type']
            : (isset($variables['service_type']) ? (string) $variables['service_type'] : null);

        $data = array_filter([
            'title' => $title,
            'body' => $body,
            'related_type' => $log->related_type,
            'related_id' => (string) ($log->related_id ?? ''),
            'event_class' => $log->event_class,
            'type' => isset($variables['notification_type'])
                ? (string) $variables['notification_type']
                : (string) ($log->notification_type ?? 'system'),
            'template_code' => $template->code,
            'order_id' => $log->related_type === 'order' && $log->related_id
                ? (string) $log->related_id
                : (isset($variables['order_id']) ? (string) $variables['order_id'] : null),
            'product_type' => $productType,
            'deep_link' => isset($variables['deep_link']) ? (string) $variables['deep_link'] : (
                $log->related_type === 'order' && $log->related_id
                    ? '/my-orders'
                    : null
            ),
            'origin' => isset($variables['origin']) ? (string) $variables['origin'] : null,
            'destination' => isset($variables['destination']) ? (string) $variables['destination'] : null,
            'departure_date' => isset($variables['departure_date']) ? (string) $variables['departure_date'] : null,
            'flight_number' => isset($variables['flight_number']) ? (string) $variables['flight_number'] : null,
            'download_url' => isset($variables['download_url']) ? (string) $variables['download_url'] : null,
            'page_url' => isset($variables['page_url']) ? (string) $variables['page_url'] : null,
            'version' => isset($variables['version']) ? (string) $variables['version'] : null,
            'version_code' => isset($variables['version_code']) ? (string) $variables['version_code'] : null,
            'force_update' => isset($variables['force_update']) ? (string) $variables['force_update'] : null,
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
                $this->deactivateUnregisteredToken($user, $deviceToken, $result);
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

    /**
     * @param  array<string, mixed>  $result
     */
    private function deactivateUnregisteredToken(User $user, string $deviceToken, array $result): void
    {
        $errorCode = (string) data_get($result, 'response.error.details.0.errorCode', '');
        $message = (string) data_get($result, 'response.error.message', '');

        if ($errorCode !== 'UNREGISTERED' && $message !== 'NotRegistered') {
            return;
        }

        UserNotificationDevice::query()
            ->where('user_id', $user->id)
            ->where('device_token', $deviceToken)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
}
