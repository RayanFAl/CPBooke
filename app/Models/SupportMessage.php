<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'support_ticket_id',
    'user_id',
    'message',
    'is_internal',
    'sender_type',
    'message_type',
    'metadata',
    'reply_to_id',
    'delivered_at',
    'seen_at',
    'attachment_path',
    'attachment_name',
    'attachment_mime',
    'attachment_size',
])]
class SupportMessage extends Model
{
    use HasFactory;

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'metadata' => 'array',
            'attachment_size' => 'integer',
            'delivered_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    /**
     * Get the ticket that owns the message.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * Get the user who authored the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent message when this message is a threaded reply.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}