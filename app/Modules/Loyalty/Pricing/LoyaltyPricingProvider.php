<?php

namespace App\Modules\Loyalty\Pricing;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyTier;
use App\Modules\Loyalty\Pricing\LoyaltyDiscountableFareResolver;
use App\Modules\Loyalty\Services\LoyaltySettingsService;
use App\Modules\Pricing\Contracts\PricingAdjustmentProvider;
use App\Modules\Pricing\DTO\PricingAdjustmentData;
use App\Modules\Pricing\DTO\PricingContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LoyaltyPricingProvider implements PricingAdjustmentProvider
{
    public function __construct(
        private readonly LoyaltySettingsService $loyaltySettingsService,
    ) {
    }

    public function collect(PricingContext $context): array
    {
        if (! $this->loyaltySettingsService->isEnabled()) {
            return [];
        }

        if ($context->user === null) {
            return [];
        }

        $profile = $context->user->loyaltyProfile()
            ->with([
                'currentTier' => fn ($query) => $query->with([
                    'benefits' => fn ($benefitsQuery) => $benefitsQuery
                        ->orderByDesc('priority')
                        ->orderBy('display_order')
                        ->orderBy('id'),
                ]),
            ])
            ->first();

        if ($profile === null || $profile->currentTier === null) {
            return [];
        }

        return $this->mapBenefitsToAdjustments($profile->currentTier, $context);
    }

    /**
     * @return array<int, PricingAdjustmentData>
     */
    private function mapBenefitsToAdjustments(LoyaltyTier $tier, PricingContext $context): array
    {
        $evaluationTime = $context->requestedAt;
        $eligibleBenefits = $tier->benefits
            ->filter(fn (LoyaltyBenefit $benefit): bool => $this->isEligibleBenefit($benefit, $context, $evaluationTime))
            ->sort(function (LoyaltyBenefit $left, LoyaltyBenefit $right): int {
                $priorityComparison = $right->priority <=> $left->priority;

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                $displayOrderComparison = $left->display_order <=> $right->display_order;

                if ($displayOrderComparison !== 0) {
                    return $displayOrderComparison;
                }

                return $left->id <=> $right->id;
            })
            ->values();

        if ($eligibleBenefits->isEmpty()) {
            return [];
        }

        $adjustments = [];
        $globalStackingAllowed = $this->loyaltySettingsService->allowsDiscountStacking();

        foreach ($eligibleBenefits as $benefit) {
            $adjustment = $this->buildAdjustment($benefit, $tier, $context);

            if ($adjustment === null) {
                continue;
            }

            if (! $globalStackingAllowed && $adjustments !== []) {
                break;
            }

            $adjustments[] = $adjustment;

            if (! $benefit->stackable) {
                break;
            }
        }

        return $adjustments;
    }

    private function isEligibleBenefit(LoyaltyBenefit $benefit, PricingContext $context, ?CarbonInterface $evaluationTime): bool
    {
        if (! $benefit->is_active) {
            return false;
        }

        if ($benefit->benefit_type !== LoyaltyBenefit::TYPE_DISCOUNT) {
            return false;
        }

        if (! in_array($benefit->value_type, [LoyaltyBenefit::VALUE_TYPE_PERCENTAGE, LoyaltyBenefit::VALUE_TYPE_FIXED], true)) {
            return false;
        }

        if (! $this->matchesServiceType($benefit, $context->serviceType)) {
            return false;
        }

        if (! $this->passesEffectiveWindow($benefit, $evaluationTime)) {
            return false;
        }

        if (! $this->passesMinimumOrderAmount($benefit, $context->baseAmount)) {
            return false;
        }

        if ($benefit->finance_sensitive && ! (bool) ($context->attributes['allow_finance_sensitive'] ?? false)) {
            return false;
        }

        return $this->resolveAppliedAmount($benefit, $this->resolveDiscountableFare($context)) > 0;
    }

    private function matchesServiceType(LoyaltyBenefit $benefit, string $serviceType): bool
    {
        $services = $benefit->applies_to_services;

        if (! is_array($services) || $services === []) {
            return true;
        }

        return in_array($serviceType, $services, true);
    }

    private function passesEffectiveWindow(LoyaltyBenefit $benefit, ?CarbonInterface $evaluationTime): bool
    {
        $timestamp = $evaluationTime ?? now();

        if ($benefit->effective_from !== null && $timestamp->lt($benefit->effective_from)) {
            return false;
        }

        if ($benefit->effective_to !== null && $timestamp->gt($benefit->effective_to)) {
            return false;
        }

        return true;
    }

    private function passesMinimumOrderAmount(LoyaltyBenefit $benefit, string $baseAmount): bool
    {
        $minimumOrderAmount = (float) ($benefit->minimum_order_amount ?? 0);

        if ($minimumOrderAmount <= 0) {
            return true;
        }

        return round((float) $baseAmount, 2) >= round($minimumOrderAmount, 2);
    }

    private function buildAdjustment(LoyaltyBenefit $benefit, LoyaltyTier $tier, PricingContext $context): ?PricingAdjustmentData
    {
        $appliedAmount = $this->resolveAppliedAmount($benefit, $this->resolveDiscountableFare($context));

        if ($appliedAmount <= 0) {
            return null;
        }

        $reason = $benefit->minimum_order_amount !== null && (float) $benefit->minimum_order_amount > 0
            ? 'min_amount_passed'
            : 'eligible';

        return new PricingAdjustmentData(
            sourceType: 'loyalty',
            sourceId: $benefit->id,
            code: $benefit->code ?: (string) $benefit->id,
            label: $this->checkoutLabel($benefit, $tier),
            adjustmentType: 'discount',
            valueType: $benefit->value_type,
            configuredValue: $benefit->value !== null ? $this->formatAmount((float) $benefit->value) : null,
            appliedAmount: $this->formatAmount($appliedAmount),
            currency: $context->currency,
            priority: (int) $benefit->priority,
            metadata: [
                'tier_id' => $tier->id,
                'rule_matched' => true,
                'reason' => $reason,
                'stackable' => (bool) $benefit->stackable,
                'finance_sensitive' => (bool) $benefit->finance_sensitive,
                'service_type' => $context->serviceType,
            ],
        );
    }

    private function resolveDiscountableFare(PricingContext $context): string
    {
        $fareAmount = $context->attributes['fare_amount'] ?? null;

        if (is_numeric($fareAmount) && (float) $fareAmount > 0) {
            return $this->formatAmount((float) $fareAmount);
        }

        $taxAmount = $context->attributes['tax_amount'] ?? null;
        $tax = is_numeric($taxAmount) && (float) $taxAmount > 0 ? (float) $taxAmount : null;

        return $this->formatAmount(LoyaltyDiscountableFareResolver::resolve(
            (float) $context->baseAmount,
            $tax,
            null,
        ));
    }

    private function resolveAppliedAmount(LoyaltyBenefit $benefit, string $fareAmount): float
    {
        $baseValue = max(0, round((float) $fareAmount, 2));

        if ($baseValue <= 0) {
            return 0.0;
        }

        $configuredValue = max(0, round((float) ($benefit->value ?? 0), 2));
        $maximumDiscountAmount = $benefit->maximum_discount_amount !== null
            ? max(0, round((float) $benefit->maximum_discount_amount, 2))
            : null;

        $appliedAmount = match ($benefit->value_type) {
            LoyaltyBenefit::VALUE_TYPE_PERCENTAGE => round($baseValue * ($configuredValue / 100), 2),
            LoyaltyBenefit::VALUE_TYPE_FIXED => $configuredValue,
            default => 0.0,
        };

        if ($maximumDiscountAmount !== null) {
            $appliedAmount = min($appliedAmount, $maximumDiscountAmount);
        }

        return min($appliedAmount, $baseValue);
    }

    private function formatAmount(float $amount): string
    {
        return number_format(max(0, round($amount, 2)), 2, '.', '');
    }

    private function checkoutLabel(LoyaltyBenefit $benefit, LoyaltyTier $tier): string
    {
        if ($benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE && $benefit->value !== null) {
            $percentage = rtrim(rtrim(number_format((float) $benefit->value, 2, '.', ''), '0'), '.');

            return sprintf('%s discount (%s%%)', $tier->name, $percentage);
        }

        return $benefit->name ?: $benefit->code ?: 'Loyalty benefit';
    }
}