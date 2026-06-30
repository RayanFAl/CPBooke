<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Loyalty\Services\LoyaltySettingsService;

class PricingVersionService
{
    public function __construct(
        private readonly LoyaltySettingsService $loyaltySettingsService,
    ) {
    }

    public function resolve(): string
    {
        return sprintf('pricing:v1|loyalty:%d', $this->loyaltySettingsService->settingsVersion());
    }
}