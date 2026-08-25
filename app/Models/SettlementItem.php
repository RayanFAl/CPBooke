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
    'expected_cost_source',
    'wallet_debit',
    'supplier_invoice_cost',
    'difference',
    'status',
    'resolution_type',
    'resolution_reason',
    'resolution_amount',
    'resolution_note',
    'resolved_by',
    'resolved_at',
    'pending_approval_id',
    'financial_transaction_id',
    'invoice_import_id',
    'metadata',
])]
class SettlementItem extends Model
{
    public const COST_SOURCE_ORDER = 'order';

    public const COST_SOURCE_PROVIDER_WALLET = 'provider_wallet';

    public const COST_SOURCE_LEDGER = 'ledger';

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
            'resolution_amount' => 'decimal:2',
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

    public function pendingApproval(): BelongsTo
    {
        return $this->belongsTo(Approval::class, 'pending_approval_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function invoiceImport(): BelongsTo
    {
        return $this->belongsTo(SettlementInvoiceImport::class, 'invoice_import_id');
    }

    public function needsReview(): bool
    {
        return in_array($this->status, [
            self::STATUS_MISSING,
            self::STATUS_EXTRA,
            self::STATUS_DIFFERENT_COST,
        ], true);
    }

    public function hasPendingApproval(): bool
    {
        return $this->pending_approval_id !== null;
    }
}
