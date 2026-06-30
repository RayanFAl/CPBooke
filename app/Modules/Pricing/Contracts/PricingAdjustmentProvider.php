<?php

namespace App\Modules\Pricing\Contracts;

use App\Modules\Pricing\DTO\PricingAdjustmentData;
use App\Modules\Pricing\DTO\PricingContext;

interface PricingAdjustmentProvider
{
    /**
     * @return array<int, PricingAdjustmentData>
     */
    public function collect(PricingContext $context): array;
}