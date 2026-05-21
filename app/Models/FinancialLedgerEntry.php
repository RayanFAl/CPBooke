<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'financial_transaction_id',
    'order_id',
    'entry_type',
    'account_code',
    'amount',
    'currency',
    'reference_type',
    'reference_id',
    'posted_at',
    'metadata',
])]
class FinancialLedgerEntry extends Model
{
    public const ENTRY_TYPE_DEBIT = 'debit';

    public const ENTRY_TYPE_CREDIT = 'credit';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Financial ledger entries are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Financial ledger entries are immutable and cannot be deleted.');
        });
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}