<?php

namespace App\Modules\Loyalty\Pricing;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltyTier;
use App\Modules\Loyalty\Services\LoyaltyCompanyRateResolver;
use App\Modules\Loyalty\Services\LoyaltySettingsService;
use App\Modules\Pricing\Contracts\PricingAdjustmentProvider;
use App\Modules\Pricing\DTO\PricingAdjustmentData;
use App\Modules\Pricing\DTO\PricingContext;
use Carbon\CarbonInterface;

class LoyaltyPricingProvider implements PricingAdjustmentProvider
{
    public function __construct(
        private readonly LoyaltySettingsService $loyaltySettingsService,
        private readonly LoyaltyCompanyRateResolver $companyRateResolver,
    ) {}

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

        // Welcome / Level 1 discount ends after the first completed order.
        if (
            filled($profile->metadata['welcome_consumed_at'] ?? null)
            || (int) $profile->completed_orders_count >= 1
        ) {
            $tier = $profile->currentTier;
            $entitlement = $profile->metadata['entitlements'][(string) $tier->id] ?? null;
            $isWelcomeTier = (bool) $tier->is_default
                || (
                    is_array($entitlement)
                    && (
                        ($entitlement['grant_reason'] ?? null) === 'welcome'
                        || (bool) ($entitlement['ends_after_first_order'] ?? false)
                    )
                );

            if ($isWelcomeTier) {
                return [];
            }
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
            ->filter(fn (LoyaltyBenefit $benefit): bool => $this->isEligibleBenefit($benefit, $tier, $context, $evaluationTime))
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

    private function isEligibleBenefit(LoyaltyBenefit $benefit, LoyaltyTier $tier, PricingContext $context, ?CarbonInterface $evaluationTime): bool
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

        return $this->resolveAppliedAmount($benefit, $tier, $this->resolveDiscountableFare($context), $context) > 0;
    }

    private function matchesServiceType(LoyaltyBenefit $benefit, string $serviceType): bool
    {
        return $benefit->appliesToServiceType($serviceType);
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
        $appliedAmount = $this->resolveAppliedAmount($benefit, $tier, $this->resolveDiscountableFare($context), $context);

        if ($appliedAmount <= 0) {
            return null;
        }

        $reason = $benefit->minimum_order_amount !== null && (float) $benefit->minimum_order_amount > 0
            ? 'min_amount_passed'
            : 'eligible';

        $resolvedPercentage = $this->resolvedPercentage($benefit, $tier, $context);
        $companyKey = LoyaltyCompanyRate::resolveCompanyKey($context->serviceType, $context->attributes);

        return new PricingAdjustmentData(
            sourceType: 'loyalty',
            sourceId: $benefit->id,
            code: $benefit->code ?: (string) $benefit->id,
            label: $this->checkoutLabel($benefit, $tier, $resolvedPercentage),
            adjustmentType: 'discount',
            valueType: $benefit->value_type,
            configuredValue: $resolvedPercentage !== null
                ? $this->formatAmount($resolvedPercentage)
                : ($benefit->value !== null ? $this->formatAmount((float) $benefit->value) : null),
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
                'company_key' => $companyKey,
                'discount_percentage' => $resolvedPercentage,
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

    private function resolveAppliedAmount(LoyaltyBenefit $benefit, LoyaltyTier $tier, string $fareAmount, PricingContext $context): float
    {
        $baseValue = max(0, round((float) $fareAmount, 2));

        if ($baseValue <= 0) {
            return 0.0;
        }

        $maximumDiscountAmount = $benefit->maximum_discount_amount !== null
            ? max(0, round((float) $benefit->maximum_discount_amount, 2))
            : null;

        if ($benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE) {
            $percentage = $this->companyRateResolver->resolvePercentage(
                $tier,
                $context->serviceType,
                $context->attributes,
                $benefit,
            );

            $appliedAmount = $percentage !== null
                ? round($baseValue * ($percentage / 100), 2)
                : 0.0;
        } elseif ($benefit->value_type === LoyaltyBenefit::VALUE_TYPE_FIXED) {
            $appliedAmount = max(0, round((float) ($benefit->value ?? 0), 2));
        } else {
            $appliedAmount = 0.0;
        }

        if ($maximumDiscountAmount !== null) {
            $appliedAmount = min($appliedAmount, $maximumDiscountAmount);
        }

        return min($appliedAmount, $baseValue);
    }

    private function resolvedPercentage(LoyaltyBenefit $benefit, LoyaltyTier $tier, PricingContext $context): ?float
    {
        if ($benefit->value_type !== LoyaltyBenefit::VALUE_TYPE_PERCENTAGE) {
            return null;
        }

        return $this->companyRateResolver->resolvePercentage(
            $tier,
            $context->serviceType,
            $context->attributes,
            $benefit,
        );
    }

    private function formatAmount(float $amount): string
    {
        return number_format(max(0, round($amount, 2)), 2, '.', '');
    }

    private function checkoutLabel(LoyaltyBenefit $benefit, LoyaltyTier $tier, ?float $resolvedPercentage = null): string
    {
        $percentage = $resolvedPercentage;

        if ($percentage === null
            && $benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE
            && $benefit->value !== null
        ) {
            $percentage = (float) $benefit->value;
        }

        if ($percentage !== null) {
            $formatted = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');

            return sprintf('%s discount (%s%%)', $tier->name, $formatted);
        }

        return $benefit->name ?: $benefit->code ?: 'Loyalty benefit';
    }
}
