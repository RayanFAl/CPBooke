<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Support\NotificationChannels;

class InAppNotificationChannel implements NotificationChannel
{
    public function channel(): string
    {
        return NotificationChannels::IN_APP;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function send(NotificationLog $log, NotificationTemplate $template, User $user, array $variables): array
    {
        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'notification_log_id' => $log->id,
            'template_code' => $template->code,
            'template_version' => $log->template_version,
            'type' => $log->notification_type,
            'title' => $log->subject,
            'message' => $log->body,
            'data' => [
                'variables' => $variables,
                'event_class' => $log->event_class,
                'audit_context' => $log->audit_context ?? [],
            ],
            'related_type' => $log->related_type,
            'related_id' => $log->related_id,
            'delivered_at' => now(),
        ]);

        return [
            'provider' => 'database',
            'notification_id' => $notification->id,
        ];
    }
}