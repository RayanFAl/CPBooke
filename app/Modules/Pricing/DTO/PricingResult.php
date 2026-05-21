<?php

namespace App\Modules\Pricing\DTO;

final readonly class PricingResult
{
    /**
     * @param  array<int, PricingAdjustmentData>  $appliedAdjustments
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public string $serviceType,
        public string $currency,
        public string $baseAmount,
        public string $discountTotal,
        public string $finalAmount,
        public string $pricingVersion,
        public array $appliedAdjustments = [],
        public array $snapshot = [],
    ) {
    }
}