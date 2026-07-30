<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'settlement_id',
    'order_id',
    'booking_reference',
    'external_booking_id',
    'supplier_cost',
    'wallet_debit',
    'supplier_invoice_cost',
    'difference',
    'status',
    'resolution_note',
    'resolved_by',
    'resolved_at',
    'metadata',
])]
class SettlementItem extends Model
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_MISSING = 'missing';

    public const STATUS_EXTRA = 'extra';

    public const STATUS_DIFFERENT_COST = 'different_cost';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_cost' => 'decimal:2',
            'wallet_debit' => 'decimal:2',
            'supplier_invoice_cost' => 'decimal:2',
            'difference' => 'decimal:2',
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function needsReview(): bool
    {
        return in_array($this->status, [
            self::STATUS_MISSING,
            self::STATUS_EXTRA,
            self::STATUS_DIFFERENT_COST,
        ], true);
    }
}
