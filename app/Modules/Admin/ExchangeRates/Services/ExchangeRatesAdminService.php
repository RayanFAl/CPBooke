<?php

namespace App\Modules\Admin\ExchangeRates\Services;

use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Modules\Admin\ExchangeRates\Events\ExchangeRateUpdated;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\ExchangeRates\Services\ExchangeRateService;
use App\Support\Rbac\RbacAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExchangeRatesAdminService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly RbacAuditLogger $rbacAuditLogger,
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    /**
     * @return Collection<int, ExchangeRate>
     */
    public function listRates(): Collection
    {
        $rates = ExchangeRate::query()
            ->supported()
            ->get();

        if ($rates->count() < count(ExchangeRate::SUPPORTED_CURRENCIES)) {
            $existing = $rates->pluck('currency_code')->all();

            foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
                if (in_array($code, $existing, true)) {
                    continue;
                }

                $one = $code === ExchangeRate::BASE_CURRENCY ? '1.00000000' : '0.00000000';

                ExchangeRate::query()->create([
                    'currency_code' => $code,
                    'buy_rate_to_lyd' => $one,
                    'sell_rate_to_lyd' => $one,
                    'rate_to_lyd' => $one,
                    'is_active' => true,
                ]);
            }

            $rates = ExchangeRate::query()
                ->supported()
                ->get();
        }

        $order = array_flip(ExchangeRate::SUPPORTED_CURRENCIES);

        return $rates
            ->sortBy(fn (ExchangeRate $rate): int => $order[$rate->currency_code] ?? 99)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, ExchangeRate>
     */
    public function updateRates(User $actor, array $payload): Collection
    {
        $updates = [];

        foreach ([ExchangeRate::CURRENCY_USD, ExchangeRate::CURRENCY_EUR] as $code) {
            $prefix = strtolower($code);
            $buyKey = "{$prefix}_buy_rate_to_lyd";
            $sellKey = "{$prefix}_sell_rate_to_lyd";

            $hasBuy = array_key_exists($buyKey, $payload) && $payload[$buyKey] !== null && $payload[$buyKey] !== '';
            $hasSell = array_key_exists($sellKey, $payload) && $payload[$sellKey] !== null && $payload[$sellKey] !== '';

            if (! $hasBuy && ! $hasSell) {
                continue;
            }

            $existing = ExchangeRate::query()->where('currency_code', $code)->first();
            $buy = $hasBuy
                ? $this->normalizeRate($payload[$buyKey], $buyKey, $code)
                : $this->formatRate((string) ($existing?->buy_rate_to_lyd ?? $existing?->rate_to_lyd ?? '0'));
            $sell = $hasSell
                ? $this->normalizeRate($payload[$sellKey], $sellKey, $code)
                : $this->formatRate((string) ($existing?->sell_rate_to_lyd ?? $existing?->rate_to_lyd ?? '0'));

            if ((float) $sell < (float) $buy) {
                throw ValidationException::withMessages([
                    $sellKey => "The {$code} sell rate must be greater than or equal to the buy rate.",
                ]);
            }

            $updates[$code] = [
                'buy' => $buy,
                'sell' => $sell,
            ];
        }

        if ($updates === []) {
            throw ValidationException::withMessages([
                'usd_buy_rate_to_lyd' => 'Provide at least one buy/sell rate to update (USD or EUR).',
            ]);
        }

        $changes = [];

        foreach ($updates as $code => $pair) {
            $rate = ExchangeRate::query()->where('currency_code', $code)->first();
            $oldBuy = $rate ? $this->formatRate((string) ($rate->buy_rate_to_lyd ?? $rate->rate_to_lyd)) : $pair['buy'];
            $oldSell = $rate ? $this->formatRate((string) ($rate->sell_rate_to_lyd ?? $rate->rate_to_lyd)) : $pair['sell'];

            if ($rate === null) {
                $rate = new ExchangeRate([
                    'currency_code' => $code,
                    'is_active' => true,
                ]);
            } elseif ($oldBuy === $pair['buy'] && $oldSell === $pair['sell']) {
                continue;
            }

            $rate->buy_rate_to_lyd = $pair['buy'];
            $rate->sell_rate_to_lyd = $pair['sell'];
            $rate->syncMidRate();
            $rate->is_active = true;
            $rate->save();

            $newBuy = $this->formatRate((string) $rate->buy_rate_to_lyd);
            $newSell = $this->formatRate((string) $rate->sell_rate_to_lyd);

            $this->rbacAuditLogger->log(
                'exchange_rates.updated',
                'exchange-rates.manage',
                $actor,
                'exchange_rate',
                $rate->id,
                [
                    'currency_code' => $code,
                    'before' => ['buy_rate_to_lyd' => $oldBuy, 'sell_rate_to_lyd' => $oldSell],
                    'after' => ['buy_rate_to_lyd' => $newBuy, 'sell_rate_to_lyd' => $newSell],
                ],
            );

            $this->auditRecorder->success(
                AuditLog::MODULE_EXCHANGE_RATES,
                'exchange_rate.updated',
                "{$code} buy {$oldBuy}→{$newBuy}, sell {$oldSell}→{$newSell} LYD",
                AuditLog::ENTITY_EXCHANGE_RATE,
                $rate->id,
                $actor,
                ['currency_code' => $code, 'buy_rate_to_lyd' => $oldBuy, 'sell_rate_to_lyd' => $oldSell],
                ['currency_code' => $code, 'buy_rate_to_lyd' => $newBuy, 'sell_rate_to_lyd' => $newSell],
                ['source' => 'admin.exchange_rates'],
            );

            $changes[] = [
                'currency_code' => $code,
                'old_buy' => $oldBuy,
                'new_buy' => $newBuy,
                'old_sell' => $oldSell,
                'new_sell' => $newSell,
            ];
        }

        ExchangeRate::query()
            ->where('currency_code', ExchangeRate::BASE_CURRENCY)
            ->update([
                'buy_rate_to_lyd' => '1.00000000',
                'sell_rate_to_lyd' => '1.00000000',
                'rate_to_lyd' => '1.00000000',
            ]);

        $this->exchangeRateService->forgetCache();

        if ($changes !== []) {
            // One customer notification for the whole save — not one push per currency.
            event(new ExchangeRateUpdated(
                changes: $changes,
                actor: $actor,
            ));
        }

        return $this->listRates();
    }

    private function normalizeRate(mixed $value, string $field, string $code): string
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => "The {$code} rate must be a number.",
            ]);
        }

        $rate = (float) $value;

        if ($rate <= 0) {
            throw ValidationException::withMessages([
                $field => "The {$code} rate must be greater than zero.",
            ]);
        }

        if ($rate > 999999999.99999999) {
            throw ValidationException::withMessages([
                $field => "The {$code} rate is too large.",
            ]);
        }

        return $this->formatRate((string) $rate);
    }

    private function formatRate(string $rate): string
    {
        return number_format((float) $rate, ExchangeRateService::CONVERSION_SCALE, '.', '');
    }
}
