<?php

namespace Tests\Unit\Pricing;

use App\Models\LoyaltySetting;
use App\Modules\Pricing\Services\PricingVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_pricing_version_with_default_loyalty_version(): void
    {
        $service = app(PricingVersionService::class);

        $this->assertSame('pricing:v1|loyalty:1', $service->resolve());
    }

    public function test_changes_when_settings_version_changes(): void
    {
        LoyaltySetting::query()->create([
            'settings_version' => 7,
        ]);

        $service = app(PricingVersionService::class);

        $this->assertSame('pricing:v1|loyalty:7', $service->resolve());
    }
}