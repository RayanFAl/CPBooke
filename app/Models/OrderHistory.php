<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'user_id',
    'action',
    'field',
    'old_value',
    'new_value',
    'created_at',
])]
class OrderHistory extends Model
{
    public $timestamps = false;

    /**
     * Get the attribute casts for the model.
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
     * Get the order that owns the history entry.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the acting user who triggered the history entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}