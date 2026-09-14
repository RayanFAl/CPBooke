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

        return Inertia::render('admin/exchange-rates/pages/Index', [
            'rates' => $rates->map(fn (ExchangeRate $rate): array => [
                'id' => $rate->id,
                'currency_code' => $rate->currency_code,
                'rate_to_lyd' => (string) $rate->rate_to_lyd,
                'is_active' => (bool) $rate->is_active,
                'is_editable' => $rate->isEditable(),
                'updated_at' => $rate->updated_at?->toIso8601String(),
            ])->values()->all(),
            'form' => [
                'usd_rate_to_lyd' => (string) ($byCode->get(ExchangeRate::CURRENCY_USD)?->rate_to_lyd ?? ''),
                'eur_rate_to_lyd' => (string) ($byCode->get(ExchangeRate::CURRENCY_EUR)?->rate_to_lyd ?? ''),
                'lyd_rate_to_lyd' => '1.00000000',
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
