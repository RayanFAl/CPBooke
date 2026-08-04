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

    public const REFERENCE_MANUAL = 'manual';

    public const REFERENCE_ORDER = 'order';

    public const REFERENCE_TEST_TOP_UP = 'test_top_up';

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
}
