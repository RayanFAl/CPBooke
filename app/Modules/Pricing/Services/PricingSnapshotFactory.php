<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\DTO\PricingAdjustmentData;

class PricingSnapshotFactory
{
    /**
     * @param  array<int, PricingAdjustmentData>  $adjustments
     * @return array<string, mixed>
     */
    public function make(
        string $serviceType,
        string $currency,
        string $baseAmount,
        string $discountTotal,
        string $finalAmount,
        string $pricingVersion,
        array $adjustments = [],
    ): array {
        return [
            'version' => $pricingVersion,
            'service_type' => $serviceType,
            'currency' => $currency,
            'base_amount' => $baseAmount,
            'discount_total' => $discountTotal,
            'final_amount' => $finalAmount,
            'adjustments' => array_map(
                static fn (PricingAdjustmentData $adjustment): array => [
                    'source' => $adjustment->sourceType,
                    'code' => $adjustment->code,
                    'type' => $adjustment->adjustmentType,
                    'configured_value' => $adjustment->configuredValue,
                    'applied_amount' => $adjustment->appliedAmount,
                ],
                $adjustments,
            ),
        ];
    }
}