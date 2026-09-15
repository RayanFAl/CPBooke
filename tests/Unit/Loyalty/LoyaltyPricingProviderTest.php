<?php

namespace Tests\Unit\Loyalty;

use App\Models\LoyaltySetting;
use App\Models\User;
use App\Modules\Loyalty\Pricing\LoyaltyPricingProvider;
use App\Modules\Pricing\DTO\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPricingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_loyalty_disabled(): void
    {
        LoyaltySetting::query()->create([
            'loyalty_enabled' => false,
        ]);

        $provider = app(LoyaltyPricingProvider::class);
        $user = User::factory()->create();

        $result = $provider->collect(new PricingContext(
            user: $user,
            serviceType: 'hotel',
            currency: 'LYD',
            baseAmount: '100.00',
            source: 'preview',
        ));

        $this->assertSame([], $result);
    }

    public function test_returns_empty_without_user(): void
    {
        $provider = app(LoyaltyPricingProvider::class);

        $result = $provider->collect(new PricingContext(
            user: null,
            serviceType: 'hotel',
            currency: 'LYD',
            baseAmount: '100.00',
            source: 'preview',
        ));

        $this->assertSame([], $result);
    }

    public function test_returns_empty_without_profile(): void
    {
        $provider = app(LoyaltyPricingProvider::class);
        $user = User::factory()->create();

        $result = $provider->collect(new PricingContext(
            user: $user,
            serviceType: 'hotel',
            currency: 'LYD',
            baseAmount: '100.00',
            source: 'preview',
        ));

        $this->assertSame([], $result);
    }

    public function test_applies_same_discount_for_hotel_insurance_and_esim(): void
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

        $tier = \App\Models\LoyaltyTier::query()->where('code', 'vip')->firstOrFail();

        \App\Models\UserLoyaltyProfile::query()->create([
            'user_id' => $customer->id,
            'current_tier_id' => $tier->id,
            'completed_orders_count' => 5,
            'lifetime_spend' => 5000,
            'period_spend' => 1200,
            'progress_percentage' => 100,
            'metadata' => [
                'entitlements' => [
                    (string) $tier->id => [
                        'tier_id' => $tier->id,
                        'tier_code' => 'vip',
                        'tier_level' => 4,
                        'qualified_at' => now()->toIso8601String(),
                        'expires_at' => now()->addMonths(12)->toIso8601String(),
                        'qualification_spend' => 25000,
                        'threshold' => 25000,
                        'duration_months' => 12,
                    ],
                ],
            ],
        ]);

        $provider = app(LoyaltyPricingProvider::class);

        foreach (['hotel', 'insurance', 'esim'] as $serviceType) {
            $result = $provider->collect(new PricingContext(
                user: $customer->fresh(),
                serviceType: $serviceType,
                currency: 'LYD',
                baseAmount: '1000.00',
                source: 'preview',
            ));

            $this->assertCount(1, $result, "Expected loyalty adjustment for {$serviceType}");
            $this->assertSame('loyalty', $result[0]->sourceType);
            $this->assertSame('150.00', $result[0]->appliedAmount);
        }
    }
}