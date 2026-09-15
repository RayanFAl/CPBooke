<?php

namespace App\Modules\ExchangeRates\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ExchangeRateService
{
    public const CACHE_KEY = 'exchange_rates.active.v2';

    public const CACHE_TTL_SECONDS = 300;

    public const CONVERSION_SCALE = 8;

    /**
     * @return array{
     *     base_currency: string,
     *     rates: array<string, array{buy: float, sell: float, mid: float}>,
     *     updated_at: string|null
     * }
     */
    public function getRatesPayload(): array
    {
        $snapshot = $this->snapshot();

        $rates = [];
        foreach ($snapshot['rates'] as $code => $row) {
            $rates[$code] = [
                'buy' => (float) $row['buy'],
                'sell' => (float) $row['sell'],
                'mid' => (float) $row['mid'],
            ];
        }

        return [
            'base_currency' => ExchangeRate::BASE_CURRENCY,
            'rates' => $rates,
            'updated_at' => $snapshot['updated_at'],
        ];
    }

    /**
     * Convert through LYD using buy / sell / mid / auto.
     *
     * auto (exchange-desk style):
     *   from → LYD uses BUY of source
     *   LYD → to uses SELL of target
     *
     * @return array{
     *     amount: float,
     *     from: string,
     *     to: string,
     *     side: string,
     *     converted_amount: float,
     *     rate: float,
     *     base_currency: string,
     *     rates_updated_at: string|null
     * }
     */
    public function convert(float|int|string $amount, string $from, string $to, ?string $side = ExchangeRate::SIDE_MID): array
    {
        $fromCode = ExchangeRate::normalizeCode($from);
        $toCode = ExchangeRate::normalizeCode($to);
        $resolvedSide = ExchangeRate::normalizeSide($side);
        $numericAmount = $this->normalizeAmount($amount);

        $this->assertSupported($fromCode);
        $this->assertSupported($toCode);

        $snapshot = $this->snapshot();
        $rates = $snapshot['rates'];

        [$sourceRate, $targetRate] = $this->resolvePairRates($rates, $fromCode, $toCode, $resolvedSide);

        if ($targetRate <= 0.0) {
            throw new InvalidArgumentException("Target exchange rate for {$toCode} must be greater than zero.");
        }

        $crossRate = $sourceRate / $targetRate;
        $converted = $numericAmount * $crossRate;

        return [
            'amount' => $this->roundMoney($numericAmount),
            'from' => $fromCode,
            'to' => $toCode,
            'side' => $resolvedSide,
            'converted_amount' => $this->roundMoney($converted),
            'rate' => $this->roundMoney($crossRate),
            'base_currency' => ExchangeRate::BASE_CURRENCY,
            'rates_updated_at' => $snapshot['updated_at'],
        ];
    }

    /**
     * @return array{
     *     rates: array<string, array{buy: string, sell: string, mid: string}>,
     *     updated_at: string|null
     * }
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

        $columns = ['currency_code', 'updated_at'];
        if (Schema::hasColumn('exchange_rates', 'buy_rate_to_lyd')) {
            $columns[] = 'buy_rate_to_lyd';
            $columns[] = 'sell_rate_to_lyd';
        }
        if (Schema::hasColumn('exchange_rates', 'rate_to_lyd')) {
            $columns[] = 'rate_to_lyd';
        }

        $rows = ExchangeRate::query()
            ->supported()
            ->active()
            ->get($columns);

        $rates = [];
        $latestUpdatedAt = null;

        foreach ($rows as $row) {
            $code = ExchangeRate::normalizeCode((string) $row->currency_code);
            $buy = (string) ($row->buy_rate_to_lyd ?? $row->rate_to_lyd ?? '0');
            $sell = (string) ($row->sell_rate_to_lyd ?? $row->rate_to_lyd ?? '0');
            $rates[$code] = $this->pairFromBuySell($buy, $sell);

            if ($row->updated_at instanceof Carbon) {
                if ($latestUpdatedAt === null || $row->updated_at->gt($latestUpdatedAt)) {
                    $latestUpdatedAt = $row->updated_at->copy();
                }
            }
        }

        foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
            if (! array_key_exists($code, $rates)) {
                $rates[$code] = $code === ExchangeRate::BASE_CURRENCY
                    ? $this->pairFromBuySell('1', '1')
                    : $this->pairFromBuySell('0', '0');
            }
        }

        $rates[ExchangeRate::BASE_CURRENCY] = $this->pairFromBuySell('1', '1');

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
        Cache::forget('exchange_rates.active'); // legacy key
    }

    /**
     * @param  array<string, array{buy: string, sell: string, mid: string}>  $rates
     * @return array{0: float, 1: float}
     */
    private function resolvePairRates(array $rates, string $fromCode, string $toCode, string $side): array
    {
        if ($side === ExchangeRate::SIDE_AUTO) {
            // Exchange-desk style: bank buys source FX from customer, sells target FX to customer.
            $sourceRate = $this->rateAsFloat($rates[$fromCode]['buy'] ?? null, $fromCode, 'buy');
            $targetRate = $this->rateAsFloat($rates[$toCode]['sell'] ?? null, $toCode, 'sell');

            return [$sourceRate, $targetRate];
        }

        $field = match ($side) {
            ExchangeRate::SIDE_BUY => 'buy',
            ExchangeRate::SIDE_SELL => 'sell',
            default => 'mid',
        };

        $sourceRate = $this->rateAsFloat($rates[$fromCode][$field] ?? null, $fromCode, $field);
        $targetRate = $this->rateAsFloat($rates[$toCode][$field] ?? null, $toCode, $field);

        return [$sourceRate, $targetRate];
    }

    /**
     * @return array{rates: array<string, array{buy: string, sell: string, mid: string}>, updated_at: string|null}
     */
    private function defaultSnapshot(): array
    {
        return [
            'rates' => [
                ExchangeRate::CURRENCY_LYD => $this->pairFromBuySell('1.00000000', '1.00000000'),
                ExchangeRate::CURRENCY_USD => $this->pairFromBuySell('9.35000000', '9.42000000'),
                ExchangeRate::CURRENCY_EUR => $this->pairFromBuySell('10.85000000', '10.93000000'),
            ],
            'updated_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rates
     * @return array<string, array{buy: string, sell: string, mid: string}>
     */
    private function normalizeRatesMap(array $rates): array
    {
        $normalized = [];

        foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
            $row = $rates[$code] ?? null;

            if (is_array($row)) {
                $buy = (string) ($row['buy'] ?? $row['mid'] ?? '0');
                $sell = (string) ($row['sell'] ?? $row['mid'] ?? '0');
            } else {
                $buy = (string) ($row ?? ($code === ExchangeRate::BASE_CURRENCY ? '1' : '0'));
                $sell = $buy;
            }

            $normalized[$code] = $this->pairFromBuySell($buy, $sell);
        }

        $normalized[ExchangeRate::BASE_CURRENCY] = $this->pairFromBuySell('1', '1');

        return $normalized;
    }

    /**
     * @return array{buy: string, sell: string, mid: string}
     */
    private function pairFromBuySell(string $buy, string $sell): array
    {
        $buyFormatted = $this->formatRate($buy);
        $sellFormatted = $this->formatRate($sell);
        $mid = $this->formatRate((string) (((float) $buyFormatted + (float) $sellFormatted) / 2));

        return [
            'buy' => $buyFormatted,
            'sell' => $sellFormatted,
            'mid' => $mid,
        ];
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

    private function rateAsFloat(?string $rate, string $code, string $side): float
    {
        if ($rate === null || ! is_numeric($rate)) {
            throw new InvalidArgumentException("Missing {$side} exchange rate for {$code}.");
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
