<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'current_tier_id',
    'next_tier_id',
    'lifetime_orders_count',
    'completed_orders_count',
    'lifetime_spend',
    'period_orders_count',
    'period_spend',
    'progress_percentage',
    'auto_upgrade_enabled',
    'last_calculated_at',
    'upgraded_at',
    'downgraded_at',
    'metadata',
])]
class UserLoyaltyProfile extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifetime_orders_count' => 'integer',
            'completed_orders_count' => 'integer',
            'lifetime_spend' => 'decimal:2',
            'period_orders_count' => 'integer',
            'period_spend' => 'decimal:2',
            'progress_percentage' => 'integer',
            'auto_upgrade_enabled' => 'boolean',
            'last_calculated_at' => 'datetime',
            'upgraded_at' => 'datetime',
            'downgraded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'current_tier_id');
    }

    public function nextTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'next_tier_id');
    }
}