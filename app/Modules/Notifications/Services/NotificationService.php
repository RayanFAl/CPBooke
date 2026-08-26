<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationDevice;
use App\Models\UserNotificationPreference;
use App\Modules\Notifications\Channels\PushNotificationChannel;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationInboxContract;
use App\Modules\Notifications\Support\NotificationTemplateSamples;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(
        private readonly NotificationEngine $notificationEngine,
        private readonly NotificationPreferenceResolver $notificationPreferenceResolver,
        private readonly PushNotificationChannel $pushNotificationChannel,
    ) {}

    public function dispatchForEvent(object $event, ?User $actor = null): void
    {
        $this->notificationEngine->dispatch($event, $actor);
    }

    public function paginateForUser(User $user, int $perPage = 15, bool $unreadOnly = false, ?string $category = null): LengthAwarePaginator
    {
        $query = UserNotification::query()
            ->whereBelongsTo($user)
            ->latest('id');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        if (is_string($category) && $category !== '') {
            $query->where(function ($inner) use ($category): void {
                $inner->where('data->variables->category', $category)
                    ->orWhereIn('template_code', NotificationInboxContract::codesForCategory($category));
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, int>
     */
    public function unreadCountByCategory(User $user): array
    {
        $counts = array_fill_keys(NotificationInboxContract::categories(), 0);

        UserNotification::query()
            ->whereBelongsTo($user)
            ->whereNull('read_at')
            ->get(['template_code', 'type', 'data'])
            ->each(function (UserNotification $notification) use (&$counts): void {
                $variables = (array) data_get($notification->data, 'variables', []);
                $category = $variables['category']
                    ?? NotificationInboxContract::category(
                        (string) $notification->template_code,
                        $variables,
                    );

                if (isset($counts[$category])) {
                    $counts[$category]++;
                }
            });

        return $counts;
    }

    public function unreadCountForUser(User $user): int
    {
        return UserNotification::query()
            ->whereBelongsTo($user)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function unreadSummaryForUser(User $user, int $limit = 10): array
    {
        $query = UserNotification::query()
            ->whereBelongsTo($user)
            ->whereNull('read_at')
            ->latest('id');

        return [
            'count' => (clone $query)->count(),
            'items' => $query->limit($limit)->get(),
        ];
    }

    public function findForUser(User $user, int|string $notificationId): UserNotification
    {
        $notification = UserNotification::query()
            ->whereBelongsTo($user)
            ->whereKey($notificationId)
            ->first();

        if (! $notification) {
            abort(404, 'Notification not found.');
        }

        return $notification;
    }

    public function markOneAsRead(User $user, int|string $notificationId): UserNotification
    {
        $notification = $this->findForUser($user, $notificationId);

        if ($notification->isUnread()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->refresh();
    }

    public function markAsRead(User $user, array $notificationIds = [], bool $markAll = false): int
    {
        $query = UserNotification::query()
            ->whereBelongsTo($user)
            ->whereNull('read_at');

        if (! $markAll) {
            $query->whereIn('id', $notificationIds);
        }

        return $query->update([
            'read_at' => now(),
        ]);
    }

    public function markAllAsRead(User $user): int
    {
        return $this->markAsRead($user, markAll: true);
    }

    public function deleteOne(User $user, int|string $notificationId): bool
    {
        $notification = $this->findForUser($user, $notificationId);

        return (bool) $notification->delete();
    }

    public function deleteAllForUser(User $user): int
    {
        return UserNotification::query()
            ->whereBelongsTo($user)
            ->delete();
    }

    /**
     * Register or refresh a push device token for the user.
     */
    public function registerDevice(
        User $user,
        string $deviceToken,
        string $platform,
        string $channel = NotificationChannels::PUSH,
        ?string $appVersion = null,
    ): UserNotificationDevice {
        $device = UserNotificationDevice::query()->updateOrCreate(
            ['device_token' => $deviceToken],
            [
                'user_id' => $user->id,
                'channel' => $channel,
                'platform' => $platform,
                'app_version' => $appVersion,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        // If the token moved between users, ensure ownership is current.
        if ((int) $device->user_id !== (int) $user->id) {
            $device->forceFill([
                'user_id' => $user->id,
                'channel' => $channel,
                'platform' => $platform,
                'app_version' => $appVersion,
                'is_active' => true,
                'last_seen_at' => now(),
            ])->save();
        }

        return $device->refresh();
    }

    /**
     * Disable or remove a push device token for the user.
     */
    public function disableDevice(User $user, string $deviceToken, bool $delete = false): UserNotificationDevice
    {
        $device = UserNotificationDevice::query()
            ->whereBelongsTo($user)
            ->where('device_token', $deviceToken)
            ->first();

        if (! $device) {
            abort(404, 'Device not found.');
        }

        if ($delete) {
            $device->delete();

            return $device;
        }

        $device->forceFill([
            'is_active' => false,
            'last_seen_at' => now(),
        ])->save();

        return $device->refresh();
    }

    public function setDeviceEnabled(User $user, string $deviceToken, bool $enabled): UserNotificationDevice
    {
        $device = UserNotificationDevice::query()
            ->whereBelongsTo($user)
            ->where('device_token', $deviceToken)
            ->first();

        if (! $device) {
            abort(404, 'Device not found.');
        }

        $device->forceFill([
            'is_active' => $enabled,
            'last_seen_at' => now(),
        ])->save();

        return $device->refresh();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function createSecurityNotification(User $user, string $title, string $body, array $meta = []): UserNotification
    {
        return UserNotification::query()->create([
            'user_id' => $user->id,
            'template_code' => 'LOGIN_ALERT',
            'type' => 'system',
            'title' => $title,
            'message' => $body,
            'data' => [
                'variables' => $meta,
                'event_class' => 'login_alert',
            ],
            'delivered_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function sendPushOnly(User $user, string $title, string $body, array $variables = []): array
    {
        $preferences = $this->notificationPreferenceResolver->preferencesFor($user);

        if (! $preferences->push_enabled) {
            return [
                'provider' => 'fcm',
                'delivered' => false,
                'reason' => 'push_disabled',
            ];
        }

        $templateCode = (string) ($variables['template_code'] ?? 'MANUAL_PUSH');

        $log = new NotificationLog([
            'subject' => $title,
            'body' => $body,
            'related_type' => null,
            'related_id' => null,
            'event_class' => (string) ($variables['topic'] ?? 'manual_push'),
            'notification_type' => (string) ($variables['notification_type'] ?? 'system'),
        ]);

        $template = new NotificationTemplate([
            'code' => $templateCode,
            'name' => $templateCode,
        ]);

        return $this->pushNotificationChannel->send($log, $template, $user, $variables);
    }

    /**
     * Send a test push (+ in-app row) so mobile can verify the channel path.
     *
     * @return array<string, mixed>
     */
    public function sendTestPush(User $user, ?string $title = null, ?string $body = null): array
    {
        $title ??= 'Test notification';
        $body ??= 'Push channel test from BookNow.';

        $inApp = UserNotification::query()->create([
            'user_id' => $user->id,
            'template_code' => 'TEST_PUSH',
            'type' => 'system',
            'title' => $title,
            'message' => $body,
            'data' => [
                'variables' => [
                    'deep_link' => '/notifications',
                ],
                'event_class' => 'test_push',
            ],
            'related_type' => null,
            'related_id' => null,
            'delivered_at' => now(),
        ]);

        $log = new NotificationLog([
            'subject' => $title,
            'body' => $body,
            'related_type' => null,
            'related_id' => null,
            'event_class' => 'test_push',
            'notification_type' => 'system',
        ]);

        $template = new NotificationTemplate([
            'code' => 'TEST_PUSH',
            'name' => 'Test Push',
        ]);

        $pushResult = $this->pushNotificationChannel->send($log, $template, $user, [
            'deep_link' => '/notifications',
            'notification_id' => (string) $inApp->id,
        ]);

        return [
            'notification' => $inApp->refresh(),
            'push' => $pushResult,
        ];
    }

    /**
     * Send one or all catalog templates through the real engine (in-app + push by default).
     *
     * @param  array<int, string>  $channels
     * @return array<string, mixed>
     */
    public function sendTestTemplates(User $user, ?string $templateCode = null, array $channels = []): array
    {
        $query = NotificationTemplate::query()->where('is_active', true)->orderBy('code');

        if (is_string($templateCode) && $templateCode !== '' && strtoupper($templateCode) !== 'ALL') {
            $query->where('code', $templateCode);
        }

        $templates = $query->get();

        if ($templates->isEmpty()) {
            abort(422, 'No active notification templates were found to test.');
        }

        $results = [];

        foreach ($templates as $template) {
            $payload = NotificationTemplateSamples::forCode($template->code);
            $payload['user_name'] = $user->full_name ?: $user->name ?: $payload['user_name'];
            $payload['notification_type'] = NotificationTemplateSamples::notificationType(
                $template->code,
                $template->category,
            );

            $requested = $channels !== []
                ? $channels
                : array_values(array_intersect(
                    $template->enabledChannels(),
                    [NotificationChannels::IN_APP, NotificationChannels::PUSH],
                ));

            $logs = $this->notificationEngine->dispatchTemplate(
                $user,
                $template,
                $payload,
                $requested,
                true,
            );

            $results[] = [
                'code' => $template->code,
                'channels' => array_map(static fn (NotificationLog $log): string => $log->channel, $logs),
                'in_app' => collect($logs)->firstWhere('channel', NotificationChannels::IN_APP)?->status,
                'push' => collect($logs)->firstWhere('channel', NotificationChannels::PUSH)?->status,
            ];
        }

        return [
            'count' => count($results),
            'templates' => $results,
        ];
    }

    public function retry(NotificationLog $log): NotificationLog
    {
        $log->forceFill([
            'status' => NotificationLog::STATUS_PENDING,
            'failed_at' => null,
        ])->save();

        SendNotificationChannelJob::dispatch($log->id)
            ->onQueue('notifications-'.$log->channel);

        return $log->refresh();
    }

    public function preferencesForUser(User $user): UserNotificationPreference
    {
        return $this->notificationPreferenceResolver->preferencesFor($user);
    }

    /**
     * @return array<string, bool>
     */
    public function passengerPreferences(User $user): array
    {
        return $this->notificationPreferenceResolver->toPassengerPayload(
            $this->preferencesForUser($user),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, bool>
     */
    public function updatePassengerPreferences(User $user, array $payload): array
    {
        $preferences = $this->notificationPreferenceResolver->updateFromPassengerPayload($user, $payload);

        return $this->notificationPreferenceResolver->toPassengerPayload($preferences);
    }
}
