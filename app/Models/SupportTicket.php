<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'ticket_number',
    'user_id',
    'order_id',
    'category',
    'priority',
    'status',
    'assigned_to',
    'subject',
    'description',
    'first_response_due_at',
    'resolution_due_at',
    'first_response_at',
    'resolved_at',
    'closed_at',
])]
class SupportTicket extends Model
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
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who opened the ticket.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related order when the ticket is linked to one.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the assigned administrative user.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the ticket messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    /**
     * Get the ticket history timeline.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(SupportTicketHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Get the resolution report captured for this ticket lifecycle.
     */
    public function resolutionReport(): HasOne
    {
        return $this->hasOne(SupportTicketResolutionReport::class, 'ticket_id');
    }

    /**
     * Get the lightweight support event logs recorded for this ticket.
     */
    public function eventLogs(): HasMany
    {
        return $this->hasMany(SupportTicketEventLog::class, 'ticket_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}