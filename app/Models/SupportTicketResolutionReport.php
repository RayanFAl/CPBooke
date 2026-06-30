<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'agent_id',
    'resolution_type',
    'root_cause',
    'actions_taken',
    'resolution_summary',
    'internal_notes',
    'customer_visible_notes',
    'status_before',
    'status_after',
    'handling_minutes',
    'escalated',
    'reopened_count',
    'satisfaction_requested',
    'metadata',
    'resolved_at',
])]
class SupportTicketResolutionReport extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handling_minutes' => 'integer',
            'escalated' => 'boolean',
            'reopened_count' => 'integer',
            'satisfaction_requested' => 'boolean',
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}