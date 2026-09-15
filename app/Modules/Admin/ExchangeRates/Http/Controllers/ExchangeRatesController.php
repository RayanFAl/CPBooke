<?php

namespace App\Modules\Admin\ExchangeRates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Modules\Admin\ExchangeRates\Http\Requests\UpdateExchangeRatesRequest;
use App\Modules\Admin\ExchangeRates\Services\ExchangeRatesAdminService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRatesController extends Controller
{
    public function __construct(
        private readonly ExchangeRatesAdminService $adminService,
    ) {
    }

    public function index(): Response
    {
        $rates = $this->adminService->listRates();
        $byCode = $rates->keyBy('currency_code');

        $mapRate = function (?ExchangeRate $rate): array {
            $buy = (string) ($rate?->buy_rate_to_lyd ?? $rate?->rate_to_lyd ?? '');
            $sell = (string) ($rate?->sell_rate_to_lyd ?? $rate?->rate_to_lyd ?? '');
            $mid = $rate ? $rate->midRateToLyd() : '';

            return [
                'id' => $rate?->id,
                'currency_code' => $rate?->currency_code,
                'buy_rate_to_lyd' => $buy,
                'sell_rate_to_lyd' => $sell,
                'mid_rate_to_lyd' => $mid,
                'rate_to_lyd' => $mid,
                'is_active' => (bool) ($rate?->is_active ?? false),
                'is_editable' => $rate?->isEditable() ?? false,
                'updated_at' => $rate?->updated_at?->toIso8601String(),
            ];
        };

        return Inertia::render('admin/exchange-rates/pages/Index', [
            'rates' => $rates->map(fn (ExchangeRate $rate): array => $mapRate($rate))->values()->all(),
            'form' => [
                'usd_buy_rate_to_lyd' => (string) ($byCode->get(ExchangeRate::CURRENCY_USD)?->buy_rate_to_lyd
                    ?? $byCode->get(ExchangeRate::CURRENCY_USD)?->rate_to_lyd
                    ?? ''),
                'usd_sell_rate_to_lyd' => (string) ($byCode->get(ExchangeRate::CURRENCY_USD)?->sell_rate_to_lyd
                    ?? $byCode->get(ExchangeRate::CURRENCY_USD)?->rate_to_lyd
                    ?? ''),
                'eur_buy_rate_to_lyd' => (string) ($byCode->get(ExchangeRate::CURRENCY_EUR)?->buy_rate_to_lyd
                    ?? $byCode->get(ExchangeRate::CURRENCY_EUR)?->rate_to_lyd
                    ?? ''),
                'eur_sell_rate_to_lyd' => (string) ($byCode->get(ExchangeRate::CURRENCY_EUR)?->sell_rate_to_lyd
                    ?? $byCode->get(ExchangeRate::CURRENCY_EUR)?->rate_to_lyd
                    ?? ''),
            ],
            'base_currency' => ExchangeRate::BASE_CURRENCY,
            'update_url' => route('admin.exchange-rates.update', absolute: false),
        ]);
    }

    public function update(UpdateExchangeRatesRequest $request): RedirectResponse
    {
        $this->adminService->updateRates($request->user(), $request->validated());

        return redirect()
            ->route('admin.exchange-rates.index')
            ->with('success', 'Exchange rates saved successfully.');
    }
}
