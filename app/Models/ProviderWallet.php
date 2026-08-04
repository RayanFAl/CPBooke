<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'provider_id',
    'currency',
    'environment',
    'balance',
    'low_balance_threshold',
    'allow_negative',
    'is_active',
])]
class ProviderWallet extends Model
{
    public const ENVIRONMENT_PRODUCTION = 'production';

    public const ENVIRONMENT_SANDBOX = 'sandbox';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'low_balance_threshold' => 'decimal:2',
            'allow_negative' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProviderWalletTransaction::class);
    }

    public function isLowBalance(): bool
    {
        if ((float) $this->balance < 0) {
            return true;
        }

        if ($this->low_balance_threshold === null) {
            return false;
        }

        return (float) $this->balance <= (float) $this->low_balance_threshold;
    }

    public function availableBalance(): float
    {
        $creditLimit = $this->allow_negative
            ? (float) ($this->provider?->credit_limit ?? 0)
            : 0.0;

        return round((float) $this->balance + max($creditLimit, 0.0), 2);
    }

    public function canCoverAmount(float $amount): bool
    {
        return $this->availableBalance() >= $amount;
    }
}
