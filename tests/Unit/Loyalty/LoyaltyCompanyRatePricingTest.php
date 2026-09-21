<?php

namespace Tests\Unit\Loyalty;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\User;
use App\Models\UserLoyaltyProfile;
use App\Modules\Loyalty\Pricing\LoyaltyPricingProvider;
use App\Modules\Pricing\DTO\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyCompanyRatePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_flight_discount_uses_airline_company_rate_for_tier(): void
    {
        LoyaltySetting::query()->create([
            'loyalty_enabled' => true,
            'settings_version' => 1,
            'default_currency' => 'LYD',
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $tier = LoyaltyTier::query()->where('level', 1)->firstOrFail();

        LoyaltyBenefit::query()->updateOrCreate(
            [
                'tier_id' => $tier->id,
                'code' => 'level_1_discount',
            ],
            [
                'name' => 'Level 1 discount',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 3,
                'applies_to_services' => ['flight', 'hotel', 'insurance', 'esim'],
                'is_active' => true,
                'priority' => 10,
                'display_order' => 1,
            ],
        );

        LoyaltyCompanyRate::query()->create([
            'tier_id' => $tier->id,
            'service_type' => 'flight',
            'company_key' => '8U',
            'company_name' => 'Afriqiyah',
            'discount_percentage' => 7,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        UserLoyaltyProfile::query()->create([
            'user_id' => $customer->id,
            'current_tier_id' => $tier->id,
            'completed_orders_count' => 0,
            'lifetime_spend' => 0,
            'period_spend' => 0,
            'progress_percentage' => 0,
            'metadata' => [
                'entitlements' => [
                    (string) $tier->id => [
                        'tier_id' => $tier->id,
                        'tier_code' => $tier->code,
                        'grant_reason' => 'welcome',
                        'ends_after_first_order' => true,
                        'expires_at' => null,
                    ],
                ],
            ],
        ]);

        $provider = app(LoyaltyPricingProvider::class);

        $result = $provider->collect(new PricingContext(
            user: $customer->fresh(),
            serviceType: 'flight',
            currency: 'LYD',
            baseAmount: '1000.00',
            source: 'preview',
            attributes: [
                'fare_amount' => '1000.00',
                'tax_amount' => '0.00',
                'airline_code' => '8U',
            ],
        ));

        $this->assertCount(1, $result);
        $this->assertSame('70.00', $result[0]->appliedAmount);
        $this->assertSame('8U', $result[0]->metadata['company_key']);
        $this->assertSame(7.0, $result[0]->metadata['discount_percentage']);
    }

    public function test_hotel_uses_single_company_rate_independent_from_flight(): void
    {
        LoyaltySetting::query()->create([
            'loyalty_enabled' => true,
            'settings_version' => 1,
            'default_currency' => 'LYD',
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $tier = LoyaltyTier::query()->where('level', 1)->firstOrFail();

        LoyaltyBenefit::query()->updateOrCreate(
            [
                'tier_id' => $tier->id,
                'code' => 'level_1_discount',
            ],
            [
                'name' => 'Level 1 discount',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 3,
                'applies_to_services' => ['flight', 'hotel', 'insurance', 'esim'],
                'is_active' => true,
                'priority' => 10,
                'display_order' => 1,
            ],
        );

        LoyaltyCompanyRate::query()->updateOrCreate(
            [
                'tier_id' => $tier->id,
                'service_type' => 'hotel',
                'company_key' => 'hotel',
            ],
            [
                'company_name' => 'Hotels',
                'discount_percentage' => 5,
                'is_active' => true,
                'sort_order' => 1000,
            ],
        );

        UserLoyaltyProfile::query()->create([
            'user_id' => $customer->id,
            'current_tier_id' => $tier->id,
            'completed_orders_count' => 0,
            'lifetime_spend' => 0,
            'period_spend' => 0,
            'progress_percentage' => 0,
            'metadata' => [
                'entitlements' => [
                    (string) $tier->id => [
                        'tier_id' => $tier->id,
                        'tier_code' => $tier->code,
                        'grant_reason' => 'welcome',
                        'ends_after_first_order' => true,
                        'expires_at' => null,
                    ],
                ],
            ],
        ]);

        $provider = app(LoyaltyPricingProvider::class);

        $result = $provider->collect(new PricingContext(
            user: $customer->fresh(),
            serviceType: 'hotel',
            currency: 'LYD',
            baseAmount: '1000.00',
            source: 'preview',
            attributes: [
                'fare_amount' => '1000.00',
                'tax_amount' => '0.00',
            ],
        ));

        $this->assertCount(1, $result);
        $this->assertSame('50.00', $result[0]->appliedAmount);
    }
}
