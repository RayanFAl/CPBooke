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

        // Ensure all three exist even before seeder runs (dev safety).
        if ($rates->count() < count(ExchangeRate::SUPPORTED_CURRENCIES)) {
            $existing = $rates->pluck('currency_code')->all();

            foreach (ExchangeRate::SUPPORTED_CURRENCIES as $code) {
                if (in_array($code, $existing, true)) {
                    continue;
                }

                ExchangeRate::query()->create([
                    'currency_code' => $code,
                    'rate_to_lyd' => $code === ExchangeRate::BASE_CURRENCY ? '1.00000000' : '0.00000000',
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
     * @param  array{usd_rate_to_lyd?: mixed, eur_rate_to_lyd?: mixed}  $payload
     * @return Collection<int, ExchangeRate>
     */
    public function updateRates(User $actor, array $payload): Collection
    {
        $updates = [];

        if (array_key_exists('usd_rate_to_lyd', $payload) && $payload['usd_rate_to_lyd'] !== null && $payload['usd_rate_to_lyd'] !== '') {
            $updates[ExchangeRate::CURRENCY_USD] = $this->normalizeRate($payload['usd_rate_to_lyd'], ExchangeRate::CURRENCY_USD);
        }

        if (array_key_exists('eur_rate_to_lyd', $payload) && $payload['eur_rate_to_lyd'] !== null && $payload['eur_rate_to_lyd'] !== '') {
            $updates[ExchangeRate::CURRENCY_EUR] = $this->normalizeRate($payload['eur_rate_to_lyd'], ExchangeRate::CURRENCY_EUR);
        }

        if ($updates === []) {
            throw ValidationException::withMessages([
                'usd_rate_to_lyd' => 'Provide at least one exchange rate to update (USD or EUR).',
            ]);
        }

        $changed = [];

        foreach ($updates as $code => $newRate) {
            $rate = ExchangeRate::query()->where('currency_code', $code)->first();

            if ($rate === null) {
                $rate = ExchangeRate::query()->create([
                    'currency_code' => $code,
                    'rate_to_lyd' => $newRate,
                    'is_active' => true,
                ]);
                $oldRate = null;
            } else {
                $oldRate = $this->formatRate((string) $rate->rate_to_lyd);

                if ($oldRate === $newRate) {
                    continue;
                }

                $rate->rate_to_lyd = $newRate;
                $rate->save();
            }

            $formattedOld = $oldRate ?? $newRate;
            $formattedNew = $this->formatRate((string) $rate->rate_to_lyd);

            $changed[] = [
                'currency_code' => $code,
                'old_rate' => $formattedOld,
                'new_rate' => $formattedNew,
                'id' => $rate->id,
            ];

            $this->rbacAuditLogger->log(
                'exchange_rates.updated',
                'exchange-rates.manage',
                $actor,
                'exchange_rate',
                $rate->id,
                [
                    'currency_code' => $code,
                    'before' => ['rate_to_lyd' => $formattedOld],
                    'after' => ['rate_to_lyd' => $formattedNew],
                ],
            );

            $this->auditRecorder->success(
                AuditLog::MODULE_EXCHANGE_RATES,
                'exchange_rate.updated',
                "{$code} rate changed: {$formattedOld} LYD → {$formattedNew} LYD",
                AuditLog::ENTITY_EXCHANGE_RATE,
                $rate->id,
                $actor,
                ['currency_code' => $code, 'rate_to_lyd' => $formattedOld],
                ['currency_code' => $code, 'rate_to_lyd' => $formattedNew],
                [
                    'source' => 'admin.exchange_rates',
                ],
            );

            event(new ExchangeRateUpdated(
                currencyCode: $code,
                oldRate: $formattedOld,
                newRate: $formattedNew,
                actor: $actor,
            ));
        }

        // Always keep LYD pinned at 1.
        ExchangeRate::query()
            ->where('currency_code', ExchangeRate::BASE_CURRENCY)
            ->update(['rate_to_lyd' => '1.00000000']);

        $this->exchangeRateService->forgetCache();

        if ($changed === []) {
            return $this->listRates();
        }

        return $this->listRates();
    }

    private function normalizeRate(mixed $value, string $code): string
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                strtolower($code).'_rate_to_lyd' => "The {$code} rate must be a number.",
            ]);
        }

        $rate = (float) $value;

        if ($rate <= 0) {
            throw ValidationException::withMessages([
                strtolower($code).'_rate_to_lyd' => "The {$code} rate must be greater than zero.",
            ]);
        }

        if ($rate > 999999999.99999999) {
            throw ValidationException::withMessages([
                strtolower($code).'_rate_to_lyd' => "The {$code} rate is too large.",
            ]);
        }

        return $this->formatRate((string) $rate);
    }

    private function formatRate(string $rate): string
    {
        return number_format((float) $rate, ExchangeRateService::CONVERSION_SCALE, '.', '');
    }
}
