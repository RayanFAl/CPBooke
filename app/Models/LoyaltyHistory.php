<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'from_tier_id',
    'to_tier_id',
    'order_id',
    'action',
    'trigger_event_class',
    'metrics_snapshot',
    'rule_snapshot',
    'notes',
    'changed_at',
])]
class LoyaltyHistory extends Model
{
    public const ACTION_UPGRADED = 'upgraded';

    public const ACTION_DOWNGRADED = 'downgraded';

    protected $table = 'loyalty_history';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metrics_snapshot' => 'array',
            'rule_snapshot' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'from_tier_id');
    }

    public function toTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'to_tier_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}