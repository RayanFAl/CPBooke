<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'legal_name',
    'key',
    'status',
    'commission_rate',
    'settlement_cycle',
    'credit_limit',
    'default_currency',
    'contact_name',
    'contact_email',
    'contact_phone',
    'integration_status',
    'contract_starts_at',
    'contract_ends_at',
    'contract_notes',
    'notes',
    'website',
    'metadata',
])]
class Provider extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const KEY_BOOKNOW = 'booknow';

    public const KEY_BOOKNOW_ESIM = 'booknow_esim';

    public const KEY_BOOKNOW_INSURANCE = 'booknow_insurance';

    public const KEY_BOOKNOW_HOTELS = 'booknow_hotels';

    public const SETTLEMENT_WEEKLY = 'weekly';

    public const SETTLEMENT_BIWEEKLY = 'biweekly';

    public const SETTLEMENT_MONTHLY = 'monthly';

    public const SETTLEMENT_MANUAL = 'manual';

    public const INTEGRATION_NOT_CONFIGURED = 'not_configured';

    public const INTEGRATION_SANDBOX = 'sandbox';

    public const INTEGRATION_LIVE = 'live';

    public const INTEGRATION_ERROR = 'error';

    public const INTEGRATION_PAUSED = 'paused';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'contract_starts_at' => 'date',
            'contract_ends_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(ProviderWallet::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @return array<int, string>
     */
    public static function settlementCycles(): array
    {
        return [
            self::SETTLEMENT_WEEKLY,
            self::SETTLEMENT_BIWEEKLY,
            self::SETTLEMENT_MONTHLY,
            self::SETTLEMENT_MANUAL,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function integrationStatuses(): array
    {
        return [
            self::INTEGRATION_NOT_CONFIGURED,
            self::INTEGRATION_SANDBOX,
            self::INTEGRATION_LIVE,
            self::INTEGRATION_ERROR,
            self::INTEGRATION_PAUSED,
        ];
    }
}
