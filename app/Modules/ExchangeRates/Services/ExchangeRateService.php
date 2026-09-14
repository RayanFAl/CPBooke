<?php

namespace App\Modules\ExchangeRates\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ExchangeRateService
{
    public const CACHE_KEY = 'exchange_rates.active';

    public const CACHE_TTL_SECONDS = 300;

    public const CONVERSION_SCALE = 8;

    /**
     * @return array{
     *     base_currency: string,
     *     rates: array<string, string>,
     *     updated_at: string|null
     * }
     */
    public function getRatesPayload(): array
    {
        $snapshot = $this->snapshot();

        return [
            'base_currency' => ExchangeRate::BASE_CURRENCY,
            'rates' => $snapshot['rates'],
            'updated_at' => $snapshot['updated_at'],
        ];
    }

    /**
     * Convert an amount between supported currencies via LYD.
     *
     * Formula: converted = amount × source_rate_to_lyd / target_rate_to_lyd
     *
     * @return array{
     *     amount: float,
     *     from: string,
     *     to: string,
     *     converted_amount: float,
     *     rate: float,
     *     base_currency: string,
     *     rates_updated_at: string|null
     * }
     */
    public function convert(float|int|string $amount, string $from, string $to): array
    {
        $fromCode = ExchangeRate::normalizeCode($from);
        $toCode = ExchangeRate::normalizeCode($to);
        $numericAmount = $this->normalizeAmount($amount);

        $this->assertSupported($fromCode);
        $this->assertSupported($toCode);

        $snapshot = $this->snapshot();
        $rates = $snapshot['rates'];

        $sourceRate = $this->rateAsFloat($rates[$fromCode] ?? null, $fromCode);
        $targetRate = $this->rateAsFloat($rates[$toCode] ?? null, $toCode);

        if ($targetRate <= 0.0) {
            throw new InvalidArgumentException("Target exchange rate for {$toCode} must be greater than zero.");
        }

        $crossRate = $sourceRate / $targetRate;
        $converted = $numericAmount * $crossRate;

        return [
            'amount' => $this->roundMoney($numericAmount),
            'from' => $fromCode,
            'to' => $toCode,
            'converted_amount' => $this->roundMoney($converted),
            'rate' => $this->roundMoney($crossRate),
            'base_currency' => ExchangeRate::BASE_CURRENCY,
            'rates_updated_at' => $snapshot['updated_at'],
        ];
    }

    /**
     * @return array{rates: array<string, string>, updated_at: string|null}
     */
    public function snapshot(): array
    {
        if (! Schema::hasTable('exchange_rates')) {
            return $this->defaultSnapshot();
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && isset($cached['rates']) && is_array($cached['rates'])) {
            return [
                'rates' => $this->normalizeRatesMap($cached['rates']),
                'updated_at' => is_string($cached['updated_at'] ?? null) ? $cached['updated_at'] : null,
            ];
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $rows = ExchangeRate::query()
            ->supported()
            ->active()
            ->get(['currency_code', 'rate_to_lyd', 'updated_at']);

        $rates = [];
        $latestUpdatedAt = null;

        foreach ($rows as $row) {
            $code = ExchangeRate::normalizeCode((string) $row->currency_code);
            $rates[$code] = $this->formatRate((string) $row->rate_to_lyd);

            if ($row->updated_at instanceof Carbon) {
                if ($latestUpdatedAt === null || $row->updated_at->gt($latestUpdatedAt)) {
                    $latestUpdatedAt = $row->updated_at->copy();
                }
            }
        }

        foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
            if (! array_key_exists($code, $rates)) {
                $rates[$code] = $code === ExchangeRate::BASE_CURRENCY
                    ? '1.00000000'
                    : '0.00000000';
            }
        }

        // Enforce LYD identity regardless of stored value.
        $rates[ExchangeRate::BASE_CURRENCY] = '1.00000000';

        $ordered = [];
        foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
            $ordered[$code] = $rates[$code];
        }

        $payload = [
            'rates' => $ordered,
            'updated_at' => $latestUpdatedAt?->utc()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $payload, self::CACHE_TTL_SECONDS);

        return $payload;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{rates: array<string, string>, updated_at: string|null}
     */
    private function defaultSnapshot(): array
    {
        return [
            'rates' => [
                ExchangeRate::CURRENCY_LYD => '1.00000000',
                ExchangeRate::CURRENCY_USD => '9.38500000',
                ExchangeRate::CURRENCY_EUR => '10.89000000',
            ],
            'updated_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rates
     * @return array<string, string>
     */
    private function normalizeRatesMap(array $rates): array
    {
        $normalized = [];

        foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
            $value = $rates[$code] ?? ($code === ExchangeRate::BASE_CURRENCY ? '1' : '0');
            $normalized[$code] = $this->formatRate((string) $value);
        }

        $normalized[ExchangeRate::BASE_CURRENCY] = '1.00000000';

        return $normalized;
    }

    private function formatRate(string $rate): string
    {
        return number_format((float) $rate, self::CONVERSION_SCALE, '.', '');
    }

    private function normalizeAmount(float|int|string $amount): float
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        $value = (float) $amount;

        if ($value < 0) {
            throw new InvalidArgumentException('Amount must be greater than or equal to zero.');
        }

        return $value;
    }

    private function rateAsFloat(?string $rate, string $code): float
    {
        if ($rate === null || ! is_numeric($rate)) {
            throw new InvalidArgumentException("Missing exchange rate for {$code}.");
        }

        $value = (float) $rate;

        if ($value < 0) {
            throw new InvalidArgumentException("Exchange rate for {$code} cannot be negative.");
        }

        return $value;
    }

    private function roundMoney(float $value): float
    {
        return round($value, self::CONVERSION_SCALE);
    }

    private function assertSupported(string $code): void
    {
        if (! ExchangeRate::isSupported($code)) {
            throw new InvalidArgumentException(
                "Unsupported currency [{$code}]. Supported currencies: ".implode(', ', ExchangeRate::SUPPORTED_CURRENCIES).'.'
            );
        }
    }
}
