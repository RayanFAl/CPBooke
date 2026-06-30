<?php

namespace Tests\Unit\Loyalty;

use App\Modules\Loyalty\Pricing\LoyaltyDiscountableFareResolver;
use PHPUnit\Framework\TestCase;

class LoyaltyDiscountableFareResolverTest extends TestCase
{
    public function test_prefers_explicit_base_fare_when_present(): void
    {
        $this->assertSame(613.0, LoyaltyDiscountableFareResolver::resolve(740.0, 127.0, 613.0));
    }

    public function test_derives_fare_from_total_minus_tax(): void
    {
        $this->assertSame(613.0, LoyaltyDiscountableFareResolver::resolve(740.0, 127.0));
    }

    public function test_uses_total_when_tax_missing(): void
    {
        $this->assertSame(500.0, LoyaltyDiscountableFareResolver::resolve(500.0));
    }

    public function test_builds_final_total_with_tax_after_discount(): void
    {
        $this->assertSame(709.35, LoyaltyDiscountableFareResolver::finalTotal(613.0, 30.65, 127.0));
    }
}
