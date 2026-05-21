<?php

namespace App\Modules\Pricing\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'source_type',
    'source_id',
    'code',
    'label',
    'adjustment_type',
    'value_type',
    'configured_value',
    'applied_amount',
    'currency',
    'priority',
    'metadata',
    'created_at',
])]
class OrderPricingAdjustment extends Model
{
    public $timestamps = false;

    protected $table = 'order_pricing_adjustments';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configured_value' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'priority' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}