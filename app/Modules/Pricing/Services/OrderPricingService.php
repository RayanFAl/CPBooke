<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\DTO\PricingContext;
use App\Modules\Pricing\DTO\PricingResult;

class OrderPricingService
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
    ) {
    }

    public function price(PricingContext $context): PricingResult
    {
        return $this->pricingEngine->price($context);
    }
}