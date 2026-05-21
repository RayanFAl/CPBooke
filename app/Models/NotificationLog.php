<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'channel',
    'template_code',
    'template_version',
    'event_class',
    'notification_type',
    'subject',
    'body',
    'variables',
    'audit_context',
    'status',
    'response_payload',
    'retry_count',
    'related_type',
    'related_id',
    'sent_at',
    'failed_at',
])]
class NotificationLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'variables' => 'array',
            'audit_context' => 'array',
            'response_payload' => 'array',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Get the recipient user for the delivery log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template used to build this delivery attempt.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_code', 'code');
    }
}