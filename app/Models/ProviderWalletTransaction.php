<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_wallet_id',
    'type',
    'amount',
    'balance_after',
    'currency',
    'reference_type',
    'reference_id',
    'description',
    'order_id',
    'created_by',
    'metadata',
])]
class ProviderWalletTransaction extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_DEBIT = 'debit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_REVERSAL = 'reversal';

    public const REFERENCE_MANUAL = 'manual';

    public const REFERENCE_ORDER = 'order';

    public const REFERENCE_FLIGHT_BOOKING = 'flight_booking';

    public const REFERENCE_HOTEL_BOOKING = 'hotel_booking';

    public const REFERENCE_ESIM_ORDER = 'esim_order';

    public const REFERENCE_INSURANCE_ORDER = 'insurance_order';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ProviderWallet::class, 'provider_wallet_id');
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

        if (in_array($this->type, [self::TYPE_DEBIT], true)) {
            return number_format(-abs($amount), 2, '.', '');
        }

        if ($this->type === self::TYPE_ADJUSTMENT) {
            return number_format($amount, 2, '.', '');
        }

        return number_format(abs($amount), 2, '.', '');
    }
}
