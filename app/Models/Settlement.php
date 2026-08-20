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
    'resolved_count',
    'review_count',
    'adjustment_total',
    'notes',
    'created_by',
    'closed_by',
    'approved_by',
    'reopened_by',
    'compared_at',
    'approved_at',
    'closed_at',
    'reopened_at',
    'reopen_reason',
    'close_history',
    'current_invoice_import_id',
    'close_snapshot',
])]
class Settlement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

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
            'adjustment_total' => 'decimal:2',
            'compared_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'close_history' => 'array',
            'close_snapshot' => 'array',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(SettlementAttachment::class);
    }

    public function invoiceImports(): HasMany
    {
        return $this->hasMany(SettlementInvoiceImport::class);
    }

    public function currentInvoiceImport(): BelongsTo
    {
        return $this->belongsTo(SettlementInvoiceImport::class, 'current_invoice_import_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isReopened(): bool
    {
        return $this->status === self::STATUS_REOPENED;
    }

    public function canMutate(): bool
    {
        return ! $this->isClosed() && ! $this->isApproved();
    }

    /**
     * @return list<string>
     */
    public static function closeableStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_APPROVED,
            self::STATUS_REOPENED,
        ];
    }
}
