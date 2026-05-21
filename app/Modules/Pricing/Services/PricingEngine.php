<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Contracts\PricingAdjustmentProvider;
use App\Modules\Pricing\DTO\PricingContext;
use App\Modules\Pricing\DTO\PricingResult;

class PricingEngine
{
    /**
     * @param  iterable<int, PricingAdjustmentProvider>  $adjustmentProviders
     */
    public function __construct(
        private readonly PricingVersionService $pricingVersionService,
        private readonly PricingSnapshotFactory $pricingSnapshotFactory,
        private readonly iterable $adjustmentProviders = [],
    ) {
    }

    public function price(PricingContext $context): PricingResult
    {
        $adjustments = [];
        $sequence = 0;

        foreach ($this->adjustmentProviders as $provider) {
            if (! $provider instanceof PricingAdjustmentProvider) {
                continue;
            }

            foreach ($provider->collect($context) as $adjustment) {
                $adjustments[] = [
                    'sequence' => $sequence++,
                    'adjustment' => $adjustment,
                ];
            }
        }

        usort($adjustments, static function (array $left, array $right): int {
            $priorityComparison = $left['adjustment']->priority <=> $right['adjustment']->priority;

            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return $left['sequence'] <=> $right['sequence'];
        });

        $orderedAdjustments = array_map(
            static fn (array $entry) => $entry['adjustment'],
            $adjustments,
        );

        $pricingVersion = $this->pricingVersionService->resolve();
        $snapshot = $this->pricingSnapshotFactory->make(
            serviceType: $context->serviceType,
            currency: $context->currency,
            baseAmount: $context->baseAmount,
            discountTotal: '0.00',
            finalAmount: $context->baseAmount,
            pricingVersion: $pricingVersion,
            adjustments: $orderedAdjustments,
        );

        return new PricingResult(
            serviceType: $context->serviceType,
            currency: $context->currency,
            baseAmount: $context->baseAmount,
            discountTotal: '0.00',
            finalAmount: $context->baseAmount,
            pricingVersion: $pricingVersion,
            appliedAdjustments: $orderedAdjustments,
            snapshot: $snapshot,
        );
    }
}