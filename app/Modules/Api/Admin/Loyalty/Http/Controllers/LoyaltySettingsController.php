<?php

namespace App\Modules\Api\Admin\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoyaltySetting;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Modules\Admin\Loyalty\Services\LoyaltySettingsAdminService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoyaltySettingsController extends Controller
{
    public function __construct(
        private readonly LoyaltySettingsAdminService $loyaltySettingsAdminService,
    ) {
    }

    public function show(): JsonResponse
    {
        return ApiResponse::success(
            ['settings' => $this->settingsPayload($this->loyaltySettingsAdminService->getSettings())],
            'Loyalty settings fetched successfully.',
        );
    }

    public function update(UpdateLoyaltySettingsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            ['settings' => $this->settingsPayload($this->loyaltySettingsAdminService->update($request, $request->user()))],
            'Loyalty settings updated successfully.',
        );
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