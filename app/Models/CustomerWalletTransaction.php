<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_wallet_id',
    'type',
    'amount',
    'balance_before',
    'balance_after',
    'currency',
    'reference_type',
    'reference_id',
    'description',
    'order_id',
    'created_by',
    'metadata',
])]
class CustomerWalletTransaction extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const TYPE_BOOKING = 'booking';

    public const TYPE_REFUND = 'refund';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_ADMIN_CREDIT = 'admin_credit';

    public const TYPE_ADMIN_DEBIT = 'admin_debit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    /** Alias used in Control Panel copy for TYPE_ADMIN_CREDIT. */
    public const TYPE_ADMIN_TOPUP = self::TYPE_ADMIN_CREDIT;

    public const REFERENCE_MANUAL = 'manual';

    public const REFERENCE_ORDER = 'order';

    public const REFERENCE_TEST_TOP_UP = 'test_top_up';

    public const REASON_CASH_RECEIVED = 'cash_received';

    public const REASON_BANK_TRANSFER = 'bank_transfer';

    public const REASON_COMPENSATION = 'compensation';

    public const REASON_PROMOTIONAL = 'promotional';

    public const REASON_CORRECTION = 'correction';

    public const REASON_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedAmount(): string
    {
        $amount = (float) $this->amount;

        if (in_array($this->type, [
            self::TYPE_DEBIT,
            self::TYPE_BOOKING,
            self::TYPE_ADMIN_DEBIT,
        ], true)) {
            return number_format(-abs($amount), 2, '.', '');
        }

        if ($this->type === self::TYPE_ADJUSTMENT) {
            return number_format($amount, 2, '.', '');
        }

        return number_format(abs($amount), 2, '.', '');
    }

    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEBIT,
            self::TYPE_BOOKING,
            self::TYPE_ADMIN_DEBIT,
        ], true);
    }

    public function reason(): ?string
    {
        $reason = $this->metadata['reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    public function note(): ?string
    {
        $note = $this->metadata['note'] ?? null;

        return is_string($note) && $note !== '' ? $note : null;
    }

    public function adminTopUpSummary(): ?string
    {
        if ($this->type !== self::TYPE_ADMIN_CREDIT) {
            return null;
        }

        $actor = $this->creator?->full_name ?: $this->creator?->name ?: 'Admin';
        $currency = $this->currency;
        $amount = number_format(abs((float) $this->amount), 2, '.', '');
        $before = number_format((float) $this->balance_before, 2, '.', '');
        $after = number_format((float) $this->balance_after, 2, '.', '');

        return sprintf(
            '%s added %s %s to Customer Wallet. Before: %s %s. Added: +%s %s. After: %s %s.',
            $actor,
            $amount,
            $currency,
            $before,
            $currency,
            $amount,
            $currency,
            $after,
            $currency,
        );
    }

    /**
     * @return list<string>
     */
    public static function adminCreditReasons(): array
    {
        return [
            self::REASON_CASH_RECEIVED,
            self::REASON_BANK_TRANSFER,
            self::REASON_COMPENSATION,
            self::REASON_PROMOTIONAL,
            self::REASON_CORRECTION,
            self::REASON_OTHER,
        ];
    }

    public static function adminCreditReasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_CASH_RECEIVED => 'Cash received',
            self::REASON_BANK_TRANSFER => 'Bank transfer',
            self::REASON_COMPENSATION => 'Compensation',
            self::REASON_PROMOTIONAL => 'Promotional credit',
            self::REASON_CORRECTION => 'Balance correction',
            default => 'Other',
        };
    }
}
