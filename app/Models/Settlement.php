<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'provider_id',
    'period_start',
    'period_end',
    'currency',
    'status',
    'expected_cost',
    'wallet_debit_total',
    'supplier_invoice_total',
    'difference',
    'orders_count',
    'matched_count',
    'review_count',
    'notes',
    'created_by',
    'closed_by',
    'compared_at',
    'closed_at',
])]
class Settlement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'expected_cost' => 'decimal:2',
            'wallet_debit_total' => 'decimal:2',
            'supplier_invoice_total' => 'decimal:2',
            'difference' => 'decimal:2',
            'compared_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canMutate(): bool
    {
        return ! $this->isClosed();
    }
}
