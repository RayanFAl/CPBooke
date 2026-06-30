<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'support_ticket_id',
    'user_id',
    'action',
    'field',
    'old_value',
    'new_value',
    'created_at',
])]
class SupportTicketHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the ticket that owns the history entry.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * Get the user who triggered the history entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}