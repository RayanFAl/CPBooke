<?php

namespace App\Modules\Loyalty\Services;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltyTier;
use Illuminate\Support\Facades\Schema;

class LoyaltyCompanyRateResolver
{
    /**
     * Resolve an explicit company rate for a tier + service + company.
     * Returns null when no configured row exists (caller should fall back).
     */
    public function find(int $tierId, string $serviceType, ?string $companyKey): ?LoyaltyCompanyRate
    {
        if (! $this->tableReady() || $companyKey === null || $companyKey === '') {
            return null;
        }

        $serviceType = strtolower(trim($serviceType));
        $normalizedKey = $serviceType === 'flight'
            ? LoyaltyCompanyRate::normalizeCompanyKey($companyKey)
            : strtolower(trim($companyKey));

        if ($normalizedKey === null || $normalizedKey === '') {
            return null;
        }

        return LoyaltyCompanyRate::query()
            ->where('tier_id', $tierId)
            ->where('service_type', $serviceType)
            ->where('company_key', $normalizedKey)
            ->first();
    }

    /**
     * Resolve the percentage that should apply for pricing.
     *
     * - Active company rate with a percentage → use it (including 0).
     * - Inactive company rate → 0 (blocked for that company).
     * - Missing rate → fall back to the tier's primary discount benefit %.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function resolvePercentage(LoyaltyTier $tier, string $serviceType, array $attributes = [], ?LoyaltyBenefit $fallbackBenefit = null): ?float
    {
        $companyKey = LoyaltyCompanyRate::resolveCompanyKey($serviceType, $attributes);
        $rate = $this->find($tier->id, $serviceType, $companyKey);

        if ($rate !== null) {
            if (! $rate->is_active) {
                return 0.0;
            }

            if ($rate->discount_percentage !== null) {
                return max(0, round((float) $rate->discount_percentage, 2));
            }
        }

        if ($fallbackBenefit !== null
            && $fallbackBenefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE
            && $fallbackBenefit->value !== null
        ) {
            return max(0, round((float) $fallbackBenefit->value, 2));
        }

        return null;
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('loyalty_company_rates');
    }
}
