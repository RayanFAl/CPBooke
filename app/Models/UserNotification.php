<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'notification_log_id',
    'template_code',
    'template_version',
    'type',
    'title',
    'message',
    'data',
    'related_type',
    'related_id',
    'read_at',
    'delivered_at',
])]
class UserNotification extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the underlying delivery log for the in-app notification.
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class, 'notification_log_id');
    }

    /**
     * Determine whether the notification is unread.
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}