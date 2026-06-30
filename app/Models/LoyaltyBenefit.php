<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tier_id',
    'code',
    'name',
    'description',
    'benefit_type',
    'value_type',
    'value',
    'configuration',
    'applies_to_services',
    'minimum_order_amount',
    'maximum_discount_amount',
    'priority',
    'stackable',
    'effective_from',
    'effective_to',
    'finance_sensitive',
    'created_by_user_id',
    'updated_by_user_id',
    'display_order',
    'is_highlighted',
    'is_active',
    'metadata',
])]
class LoyaltyBenefit extends Model
{
    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_SUPPORT = 'support';

    public const TYPE_OFFER = 'offer';

    public const TYPE_UPGRADE = 'upgrade';

    public const TYPE_SERVICE = 'service';

    public const VALUE_TYPE_PERCENTAGE = 'percentage';

    public const VALUE_TYPE_FIXED = 'fixed';

    public const VALUE_TYPE_FLAG = 'flag';

    public const VALUE_TYPE_TEXT = 'text';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'configuration' => 'array',
            'applies_to_services' => 'array',
            'minimum_order_amount' => 'decimal:2',
            'maximum_discount_amount' => 'decimal:2',
            'priority' => 'integer',
            'stackable' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'finance_sensitive' => 'boolean',
            'display_order' => 'integer',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }
}