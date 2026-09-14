<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'currency_code',
    'rate_to_lyd',
    'is_active',
])]
class ExchangeRate extends Model
{
    public const CURRENCY_LYD = 'LYD';

    public const CURRENCY_USD = 'USD';

    public const CURRENCY_EUR = 'EUR';

    public const BASE_CURRENCY = self::CURRENCY_LYD;

    /**
     * Supported currencies for this phase (strict allow-list).
     *
     * @var list<string>
     */
    public const SUPPORTED_CURRENCIES = [
        self::CURRENCY_LYD,
        self::CURRENCY_USD,
        self::CURRENCY_EUR,
    ];

    /**
     * Currencies that admins may edit (LYD is fixed at 1).
     *
     * @var list<string>
     */
    public const EDITABLE_CURRENCIES = [
        self::CURRENCY_USD,
        self::CURRENCY_EUR,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_to_lyd' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSupported(Builder $query): Builder
    {
        return $query->whereIn('currency_code', self::SUPPORTED_CURRENCIES);
    }

    public function isEditable(): bool
    {
        return in_array($this->currency_code, self::EDITABLE_CURRENCIES, true);
    }

    public function isBase(): bool
    {
        return $this->currency_code === self::BASE_CURRENCY;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function isSupported(string $code): bool
    {
        return in_array(self::normalizeCode($code), self::SUPPORTED_CURRENCIES, true);
    }

    public static function isEditableCode(string $code): bool
    {
        return in_array(self::normalizeCode($code), self::EDITABLE_CURRENCIES, true);
    }
}
