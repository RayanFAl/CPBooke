<?php

namespace App\Modules\Admin\Loyalty\Http\Controllers;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyRule;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltyBenefitRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltyRuleRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltyTierRequest;
use App\Modules\Admin\Loyalty\Services\LoyaltyAdminService;
use App\Modules\Admin\Loyalty\Services\LoyaltySettingsAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController
{
    public function __construct(
        private readonly LoyaltyAdminService $loyaltyAdminService,
        private readonly LoyaltySettingsAdminService $loyaltySettingsAdminService,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize('loyalty.view');

        $canManageSettings = Gate::allows('loyalty.settings.manage');

        return Inertia::render('admin/loyalty/pages/Index', [
            'dashboard' => $this->loyaltyAdminService->dashboard(),
            'settings' => $canManageSettings
                ? $this->settingsPayload($this->loyaltySettingsAdminService->getSettings())
                : null,
            'canManageSettings' => $canManageSettings,
            'settingsUpdateUrl' => $canManageSettings ? route('admin.loyalty.settings.update') : null,
        ]);
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
            'data' => $this->settingsPayload($this->loyaltySettingsAdminService->getSettings()),
        ]);
    }

    public function updateSettings(UpdateLoyaltySettingsRequest $request): JsonResponse
    {
        Gate::authorize('loyalty.settings.manage');

        $settings = $this->loyaltySettingsAdminService->update($request, $request->user());

        return response()->json([
            'success' => true,
            'data' => $this->settingsPayload($settings),
        ]);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function settingsPayload(LoyaltySetting $settings): array
    {
        return [
            'loyalty_enabled' => (bool) $settings->loyalty_enabled,
            'auto_upgrade_enabled' => (bool) $settings->auto_upgrade_enabled,
            'auto_downgrade_enabled' => (bool) $settings->auto_downgrade_enabled,
            'visible_in_mobile_app' => (bool) $settings->visible_in_mobile_app,
            'allow_discount_stacking' => (bool) $settings->allow_discount_stacking,
            'default_currency' => (string) $settings->default_currency,
            'max_global_discount_amount' => $settings->max_global_discount_amount,
            'minimum_discountable_order_amount' => $settings->minimum_discountable_order_amount,
            'settings_version' => (int) $settings->settings_version,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}