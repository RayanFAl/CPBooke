<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tier_id',
    'rule_type',
    'name',
    'min_completed_orders',
    'min_lifetime_spend',
    'min_period_orders',
    'min_period_spend',
    'period_days',
    'allow_downgrade',
    'is_active',
    'priority',
    'metadata',
])]
class LoyaltyRule extends Model
{
    public const TYPE_UPGRADE = 'upgrade';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_completed_orders' => 'integer',
            'min_lifetime_spend' => 'decimal:2',
            'min_period_orders' => 'integer',
            'min_period_spend' => 'decimal:2',
            'period_days' => 'integer',
            'allow_downgrade' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }
}