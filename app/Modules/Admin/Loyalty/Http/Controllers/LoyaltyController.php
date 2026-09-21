<?php

namespace App\Modules\Admin\Loyalty\Http\Controllers;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTier;
use App\Modules\Admin\Loyalty\Http\Requests\StoreLoyaltyTierRequest;
use App\Modules\Admin\Loyalty\Http\Requests\SyncLoyaltyCompanyRatesRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltyBenefitRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltyRuleRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltyTierRequest;
use App\Modules\Admin\Loyalty\Services\LoyaltyAdminService;
use App\Modules\Admin\Loyalty\Services\LoyaltyCompanyRateAdminService;
use App\Modules\Admin\Loyalty\Services\LoyaltySettingsAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController
{
    public function __construct(
        private readonly LoyaltyAdminService $loyaltyAdminService,
        private readonly LoyaltySettingsAdminService $loyaltySettingsAdminService,
        private readonly LoyaltyCompanyRateAdminService $loyaltyCompanyRateAdminService,
    ) {}

    public function index(): Response
    {
        Gate::authorize('loyalty.view');

        $settings = $this->loyaltySettingsAdminService->getSettings();

        return Inertia::render('admin/loyalty/pages/Index', [
            'dashboard' => $this->loyaltyAdminService->dashboard(),
            'program' => [
                'loyalty_enabled' => (bool) $settings->loyalty_enabled,
                'default_currency' => (string) $settings->default_currency,
            ],
            'settings' => $this->loyaltySettingsAdminService->settingsPayload($settings),
            'settings_update_url' => route('admin.loyalty.settings.update'),
            'company_rates' => $this->loyaltyCompanyRateAdminService->matrix(),
            'company_rates_sync_url' => route('admin.loyalty.company-rates.sync'),
            'company_rates_url' => route('admin.loyalty.company-rates.show'),
            'can_manage_settings' => Gate::allows('loyalty.settings.manage'),
            'can_manage_company_rates' => Gate::allows('loyalty.manage-benefits'),
        ]);
    }

    public function promo(): Response
    {
        Gate::authorize('loyalty.view');

        $settings = $this->loyaltySettingsAdminService->getSettings();

        return Inertia::render('admin/promo/pages/Index', [
            'settings' => $this->loyaltySettingsAdminService->settingsPayload($settings),
            'settings_update_url' => route('admin.loyalty.settings.update'),
            'can_manage_settings' => Gate::allows('loyalty.settings.manage'),
        ]);
    }

    public function storeTier(StoreLoyaltyTierRequest $request): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $tier = $this->loyaltyAdminService->createTier($request->validated());

        return redirect()
            ->route('admin.loyalty.index')
            ->with('success', "Loyalty level created: {$tier->name}.");
    }

    public function duplicateTier(LoyaltyTier $loyaltyTier): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $tier = $this->loyaltyAdminService->duplicateTier($loyaltyTier);

        return redirect()
            ->route('admin.loyalty.index')
            ->with('success', "Loyalty level copied: {$tier->name}.");
    }

    public function updateTier(UpdateLoyaltyTierRequest $request, LoyaltyTier $loyaltyTier): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $this->loyaltyAdminService->updateTier($loyaltyTier, $request->validated());

        return redirect()
            ->route('admin.loyalty.index')
            ->with('success', 'Loyalty tier updated successfully.');
    }

    public function updateRule(UpdateLoyaltyRuleRequest $request, LoyaltyRule $loyaltyRule): RedirectResponse
    {
        Gate::authorize('loyalty.manage-rules');

        $this->loyaltyAdminService->updateRule($loyaltyRule, $request->validated());

        return redirect()
            ->route('admin.loyalty.index')
            ->with('success', 'Loyalty rule updated successfully.');
    }

    public function updateBenefit(UpdateLoyaltyBenefitRequest $request, LoyaltyBenefit $loyaltyBenefit): RedirectResponse
    {
        Gate::authorize('loyalty.manage-benefits');

        $this->loyaltyAdminService->updateBenefit($loyaltyBenefit, $request->validated());

        return redirect()
            ->route('admin.loyalty.index')
            ->with('success', 'Loyalty benefit updated successfully.');
    }

    public function showSettings(): JsonResponse
    {
        Gate::authorize('loyalty.settings.manage');

        return response()->json([
            'success' => true,
            'data' => $this->loyaltySettingsAdminService->settingsPayload(
                $this->loyaltySettingsAdminService->getSettings(),
            ),
        ]);
    }

    public function updateSettings(UpdateLoyaltySettingsRequest $request): JsonResponse
    {
        Gate::authorize('loyalty.settings.manage');

        $settings = $this->loyaltySettingsAdminService->update($request, $request->user());

        return response()->json([
            'success' => true,
            'data' => $this->loyaltySettingsAdminService->settingsPayload($settings),
        ]);
    }

    public function showCompanyRates(Request $request): JsonResponse
    {
        Gate::authorize('loyalty.view');

        return response()->json([
            'success' => true,
            'data' => $this->loyaltyCompanyRateAdminService->matrix(
                refreshAirlines: (bool) $request->boolean('refresh_airlines'),
            ),
        ]);
    }

    public function syncCompanyRates(SyncLoyaltyCompanyRatesRequest $request): JsonResponse
    {
        Gate::authorize('loyalty.manage-benefits');

        if ($request->boolean('refresh_airlines')) {
            $this->loyaltyCompanyRateAdminService->matrix(refreshAirlines: true);
        }

        $matrix = $this->loyaltyCompanyRateAdminService->syncRates($request->validated('rates'));

        return response()->json([
            'success' => true,
            'data' => $matrix,
        ]);
    }
}
