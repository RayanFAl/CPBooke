<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(
        private readonly NotificationEngine $notificationEngine,
        private readonly NotificationPreferenceResolver $notificationPreferenceResolver,
    ) {
    }

    public function dispatchForEvent(object $event, ?User $actor = null): void
    {
        $this->notificationEngine->dispatch($event, $actor);
    }

    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return UserNotification::query()
            ->whereBelongsTo($user)
            ->latest('id')
            ->paginate($perPage);
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

    public function preferencesForUser(User $user): \App\Models\UserNotificationPreference
    {
        return $this->notificationPreferenceResolver->preferencesFor($user);
    }
}