<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'type',
    'status',
    'amount',
    'currency',
    'performed_by_type',
    'performed_by_id',
    'source',
    'source_id',
    'reason',
    'metadata',
    'debit_account',
    'credit_account',
    'reference_type',
    'reference_id',
])]
class FinancialTransaction extends Model
{
    public const ACCOUNT_CASH = 'cash';

    public const ACCOUNT_CUSTOMER_LIABILITY = 'customer_liability';

    public const ACCOUNT_REVENUE = 'revenue';

    public const ACCOUNT_COMMISSION_INCOME = 'commission_income';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_COMPENSATION = 'compensation';

    public const TYPE_REVERSAL = 'reversal';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    public const SOURCE_ORDER_CREATION = 'order_creation';

    public const SOURCE_SUPPORT_TICKET = 'support_ticket';

    public const SOURCE_PAYMENT_STATUS_PAID = 'payment_status_paid';

    public const SOURCE_PAYMENT_STATUS_PARTIALLY_REFUNDED = 'payment_status_partially_refunded';

    public const SOURCE_PAYMENT_STATUS_REFUNDED = 'payment_status_refunded';

    public const SOURCE_CUSTOMER_WALLET = 'customer_wallet';

    public const SOURCE_SETTLEMENT_ADJUSTMENT = 'settlement_adjustment';

    public const TYPE_COMMISSION = 'commission';

    public const TYPE_PAYOUT = 'payout';

    public const REFERENCE_TYPE_ORDER = 'order';

    public const REFERENCE_TYPE_SETTLEMENT_ITEM = 'settlement_item';

    public const PERFORMED_BY_TYPE_USER = 'user';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the supported transaction types.
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_PAYMENT,
            self::TYPE_REFUND,
            self::TYPE_COMPENSATION,
            self::TYPE_REVERSAL,
            self::TYPE_ADJUSTMENT,
            self::TYPE_COMMISSION,
            self::TYPE_PAYOUT,
        ];
    }

    /**
     * Resolve the ledger mapping for a transaction type.
     *
     * @return array{debit_account: string, credit_account: string}
     */
    public static function ledgerMappingForType(string $type): array
    {
        return match ($type) {
            self::TYPE_PAYMENT => [
                'debit_account' => self::ACCOUNT_CASH,
                'credit_account' => self::ACCOUNT_CUSTOMER_LIABILITY,
            ],
            self::TYPE_REFUND => [
                'debit_account' => self::ACCOUNT_CUSTOMER_LIABILITY,
                'credit_account' => self::ACCOUNT_CASH,
            ],
            self::TYPE_COMPENSATION,
            self::TYPE_ADJUSTMENT => [
                'debit_account' => self::ACCOUNT_REVENUE,
                'credit_account' => self::ACCOUNT_CUSTOMER_LIABILITY,
            ],
            self::TYPE_REVERSAL => [
                'debit_account' => self::ACCOUNT_CASH,
                'credit_account' => self::ACCOUNT_CUSTOMER_LIABILITY,
            ],
            self::TYPE_COMMISSION => [
                'debit_account' => self::ACCOUNT_REVENUE,
                'credit_account' => self::ACCOUNT_COMMISSION_INCOME,
            ],
            default => [
                'debit_account' => self::ACCOUNT_CUSTOMER_LIABILITY,
                'credit_account' => self::ACCOUNT_CASH,
            ],
        };
    }

    /**
     * Build a ledger preview row from the transaction.
     *
     * @return array<string, int|string|null>
     */
    public function toLedgerPreview(): array
    {
        $mapping = self::ledgerMappingForType($this->type);

        return [
            'transaction_id' => $this->id,
            'type' => $this->type,
            'status' => $this->status ?: self::STATUS_EXECUTED,
            'debit_account' => $this->debit_account ?: $mapping['debit_account'],
            'credit_account' => $this->credit_account ?: $mapping['credit_account'],
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'currency' => $this->currency,
            'source' => $this->source,
            'source_id' => $this->source_id,
            'reference_type' => $this->reference_type ?: self::REFERENCE_TYPE_ORDER,
            'reference_id' => $this->reference_id ?: $this->order_id,
        ];
    }

    /**
     * Get the related order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the immutable ledger entries posted for this transaction.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedgerEntry::class)
            ->orderBy('id');
    }
}