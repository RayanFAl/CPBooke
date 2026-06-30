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
}