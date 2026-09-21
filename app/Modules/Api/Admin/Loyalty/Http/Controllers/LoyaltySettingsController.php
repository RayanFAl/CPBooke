<?php

namespace App\Modules\Api\Admin\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Modules\Admin\Loyalty\Services\LoyaltySettingsAdminService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoyaltySettingsController extends Controller
{
    public function __construct(
        private readonly LoyaltySettingsAdminService $loyaltySettingsAdminService,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(
            ['settings' => $this->loyaltySettingsAdminService->settingsPayload(
                $this->loyaltySettingsAdminService->getSettings(),
            )],
            'Loyalty settings fetched successfully.',
        );
    }

    public function update(UpdateLoyaltySettingsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            ['settings' => $this->loyaltySettingsAdminService->settingsPayload(
                $this->loyaltySettingsAdminService->update($request, $request->user()),
            )],
            'Loyalty settings updated successfully.',
        );
    }
}
