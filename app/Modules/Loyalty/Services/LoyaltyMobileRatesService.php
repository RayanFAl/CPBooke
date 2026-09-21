<?php

namespace App\Modules\Loyalty\Services;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class LoyaltyMobileRatesService
{
    public function __construct(
        private readonly LoyaltyAirlinesCatalogService $airlinesCatalog,
        private readonly LoyaltyCompanyRateResolver $companyRateResolver,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    /**
     * Full loyalty snapshot for the mobile app.
     *
     * @return array<string, mixed>
     */
    public function profile(User $user): array
    {
        $payload = $this->loyaltyService->profilePayload($user);
        $payload['rates'] = $this->ratesForUser($user);

        return $payload;
    }

    /**
     * Discount rates for the user's current tier, grouped by service.
     *
     * @return array<string, mixed>
     */
    public function ratesForUser(User $user): array
    {
        $settings = LoyaltySetting::current();
        $enabled = (bool) $settings->loyalty_enabled && (bool) $settings->visible_in_mobile_app;

        $profile = $user->loyaltyProfile()
            ->with([
                'currentTier.benefits' => fn ($query) => $query
                    ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
                    ->where('value_type', LoyaltyBenefit::VALUE_TYPE_PERCENTAGE)
                    ->where('is_active', true)
                    ->orderByDesc('is_highlighted')
                    ->orderBy('display_order'),
            ])
            ->first();

        $tier = $profile?->currentTier;

        if (! $enabled || $tier === null) {
            return [
                'loyalty_enabled' => $enabled,
                'current_tier' => null,
                'default_discount_percentage' => null,
                'services' => [
                    'flight' => ['companies' => []],
                    'hotel' => ['companies' => []],
                    'esim' => ['companies' => []],
                    'insurance' => ['companies' => []],
                ],
            ];
        }

        $default = $this->defaultDiscountPercentage($tier);
        $airlines = $this->airlinesCatalog->fetch()['airlines'];
        $storedRates = $this->storedRatesForTier($tier->id);

        return [
            'loyalty_enabled' => true,
            'current_tier' => [
                'id' => $tier->id,
                'level' => $tier->level,
                'code' => $tier->code,
                'name' => $tier->name,
            ],
            'default_discount_percentage' => $default,
            'services' => [
                'flight' => [
                    'companies' => $this->flightCompanies($tier, $airlines, $storedRates, $default),
                ],
                'hotel' => [
                    'companies' => $this->catalogServiceCompanies($tier, Order::SERVICE_TYPE_HOTEL, $storedRates, $default),
                ],
                'esim' => [
                    'companies' => $this->catalogServiceCompanies($tier, Order::SERVICE_TYPE_ESIM, $storedRates, $default),
                ],
                'insurance' => [
                    'companies' => $this->catalogServiceCompanies($tier, Order::SERVICE_TYPE_INSURANCE, $storedRates, $default),
                ],
            ],
        ];
    }

    /**
     * Resolve the exact discount percentage for checkout.
     *
     * @return array<string, mixed>
     */
    public function resolve(User $user, string $serviceType, ?string $airlineCode = null): array
    {
        $serviceType = strtolower(trim($serviceType));
        $attributes = [];

        if ($serviceType === Order::SERVICE_TYPE_FLIGHT && $airlineCode !== null) {
            $attributes['airline_code'] = $airlineCode;
        }

        $profile = $user->loyaltyProfile()
            ->with([
                'currentTier.benefits' => fn ($query) => $query
                    ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
                    ->where('is_active', true)
                    ->orderByDesc('priority')
                    ->orderBy('display_order'),
            ])
            ->first();

        $tier = $profile?->currentTier;
        $settings = LoyaltySetting::current();
        $enabled = (bool) $settings->loyalty_enabled;

        if (! $enabled || $tier === null) {
            return [
                'applies' => false,
                'service_type' => $serviceType,
                'company_key' => LoyaltyCompanyRate::resolveCompanyKey($serviceType, $attributes),
                'discount_percentage' => null,
                'checkout_label' => null,
                'reason' => ! $enabled ? 'loyalty_disabled' : 'no_tier',
            ];
        }

        $fallbackBenefit = $tier->benefits
            ->first(fn (LoyaltyBenefit $benefit): bool => $benefit->benefit_type === LoyaltyBenefit::TYPE_DISCOUNT
                && $benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE);

        $percentage = $this->companyRateResolver->resolvePercentage(
            $tier,
            $serviceType,
            $attributes,
            $fallbackBenefit,
        );

        $companyKey = LoyaltyCompanyRate::resolveCompanyKey($serviceType, $attributes);
        $applies = $percentage !== null && $percentage > 0;
        $formatted = $percentage !== null
            ? rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.')
            : null;

        return [
            'applies' => $applies,
            'service_type' => $serviceType,
            'company_key' => $companyKey,
            'discount_percentage' => $percentage,
            'checkout_label' => $formatted !== null
                ? sprintf('%s discount (%s%%)', $tier->name, $formatted)
                : null,
            'tier' => [
                'id' => $tier->id,
                'level' => $tier->level,
                'code' => $tier->code,
                'name' => $tier->name,
            ],
            'reason' => $applies ? 'eligible' : ($percentage === 0.0 ? 'zero_rate' : 'no_rate'),
        ];
    }

    /**
     * @return array<string, LoyaltyCompanyRate>
     */
    private function storedRatesForTier(int $tierId): array
    {
        if (! Schema::hasTable('loyalty_company_rates')) {
            return [];
        }

        return LoyaltyCompanyRate::query()
            ->where('tier_id', $tierId)
            ->get()
            ->keyBy(fn (LoyaltyCompanyRate $rate): string => $rate->service_type.'::'.$rate->company_key)
            ->all();
    }

    /**
     * @param  array<int, array{code: string, name: string, logo_url?: string}>  $airlines
     * @param  array<string, LoyaltyCompanyRate>  $storedRates
     * @return array<int, array<string, mixed>>
     */
    private function flightCompanies(LoyaltyTier $tier, array $airlines, array $storedRates, ?float $default): array
    {
        $companies = [];

        foreach ($airlines as $airline) {
            $key = 'flight::'.$airline['code'];
            $rate = $storedRates[$key] ?? null;
            $percentage = $this->companyRateResolver->resolvePercentage(
                $tier,
                Order::SERVICE_TYPE_FLIGHT,
                ['airline_code' => $airline['code']],
                $this->fallbackBenefit($tier),
            );

            $companies[] = [
                'code' => $airline['code'],
                'name' => $airline['name'],
                'logo_url' => $airline['logo_url'] ?? null,
                'discount_percentage' => $percentage ?? $default,
                'is_active' => $rate?->is_active ?? true,
            ];
        }

        // Include locally configured airlines missing from the live catalog.
        foreach ($storedRates as $rate) {
            if ($rate->service_type !== Order::SERVICE_TYPE_FLIGHT) {
                continue;
            }

            if (collect($companies)->contains(fn (array $row): bool => $row['code'] === $rate->company_key)) {
                continue;
            }

            $percentage = $rate->is_active && $rate->discount_percentage !== null
                ? (float) $rate->discount_percentage
                : ($rate->is_active ? $default : 0.0);

            $companies[] = [
                'code' => $rate->company_key,
                'name' => $rate->company_name ?: $rate->company_key,
                'logo_url' => null,
                'discount_percentage' => $percentage,
                'is_active' => (bool) $rate->is_active,
            ];
        }

        usort($companies, fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $companies;
    }

    /**
     * @param  array<string, LoyaltyCompanyRate>  $storedRates
     * @return array<int, array<string, mixed>>
     */
    private function catalogServiceCompanies(LoyaltyTier $tier, string $serviceType, array $storedRates, ?float $default): array
    {
        $companyKey = LoyaltyCompanyRate::resolveCompanyKey($serviceType);
        $catalog = collect(LoyaltyCompanyRate::catalogCompanies())
            ->firstWhere('service_type', $serviceType);

        $rate = $storedRates[$serviceType.'::'.$companyKey] ?? null;
        $percentage = $this->companyRateResolver->resolvePercentage(
            $tier,
            $serviceType,
            [],
            $this->fallbackBenefit($tier),
        );

        return [[
            'code' => $companyKey,
            'name' => $catalog['company_name'] ?? ucfirst($serviceType),
            'logo_url' => null,
            'discount_percentage' => $percentage ?? $default,
            'is_active' => $rate?->is_active ?? true,
        ]];
    }

    private function defaultDiscountPercentage(LoyaltyTier $tier): ?float
    {
        $benefit = $this->fallbackBenefit($tier);

        if ($benefit === null || $benefit->value === null) {
            return null;
        }

        return round((float) $benefit->value, 2);
    }

    private function fallbackBenefit(LoyaltyTier $tier): ?LoyaltyBenefit
    {
        if ($tier->relationLoaded('benefits')) {
            return $tier->benefits
                ->first(fn (LoyaltyBenefit $benefit): bool => $benefit->benefit_type === LoyaltyBenefit::TYPE_DISCOUNT
                    && $benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE
                    && $benefit->is_active);
        }

        return $tier->benefits()
            ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
            ->where('value_type', LoyaltyBenefit::VALUE_TYPE_PERCENTAGE)
            ->where('is_active', true)
            ->orderByDesc('is_highlighted')
            ->orderBy('display_order')
            ->first();
    }
}
