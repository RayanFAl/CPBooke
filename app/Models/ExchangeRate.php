<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'currency_code',
    'buy_rate_to_lyd',
    'sell_rate_to_lyd',
    'rate_to_lyd',
    'is_active',
])]
class ExchangeRate extends Model
{
    public const CURRENCY_LYD = 'LYD';

    public const CURRENCY_USD = 'USD';

    public const CURRENCY_EUR = 'EUR';

    public const BASE_CURRENCY = self::CURRENCY_LYD;

    public const SIDE_BUY = 'buy';

    public const SIDE_SELL = 'sell';

    public const SIDE_MID = 'mid';

    public const SIDE_AUTO = 'auto';

    /**
     * @var list<string>
     */
    public const SUPPORTED_CURRENCIES = [
        self::CURRENCY_LYD,
        self::CURRENCY_USD,
        self::CURRENCY_EUR,
    ];

    /**
     * @var list<string>
     */
    public const EDITABLE_CURRENCIES = [
        self::CURRENCY_USD,
        self::CURRENCY_EUR,
    ];

    /**
     * @var list<string>
     */
    public const CONVERSION_SIDES = [
        self::SIDE_BUY,
        self::SIDE_SELL,
        self::SIDE_MID,
        self::SIDE_AUTO,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buy_rate_to_lyd' => 'decimal:8',
            'sell_rate_to_lyd' => 'decimal:8',
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

    public function midRateToLyd(): string
    {
        $buy = (float) $this->buy_rate_to_lyd;
        $sell = (float) $this->sell_rate_to_lyd;

        return number_format(($buy + $sell) / 2, 8, '.', '');
    }

    public function syncMidRate(): void
    {
        $this->rate_to_lyd = $this->midRateToLyd();
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

    public static function normalizeSide(?string $side): string
    {
        $normalized = strtolower(trim((string) ($side ?: self::SIDE_MID)));

        return in_array($normalized, self::CONVERSION_SIDES, true)
            ? $normalized
            : self::SIDE_MID;
    }
}
