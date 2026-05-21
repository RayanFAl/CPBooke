<?php

namespace Tests\Unit\Loyalty;

use App\Models\LoyaltySetting;
use App\Modules\Loyalty\Services\LoyaltySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltySettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_defaults_when_table_empty(): void
    {
        $service = app(LoyaltySettingsService::class);

        $settings = $service->current();

        $this->assertFalse($settings->exists);
        $this->assertTrue($settings->loyalty_enabled);
        $this->assertTrue($settings->auto_upgrade_enabled);
        $this->assertFalse($settings->auto_downgrade_enabled);
        $this->assertTrue($settings->visible_in_mobile_app);
        $this->assertFalse($settings->allow_discount_stacking);
        $this->assertSame('LYD', $settings->default_currency);
        $this->assertSame(1, $settings->settings_version);
    }

    public function test_returns_persisted_settings(): void
    {
        LoyaltySetting::query()->create([
            'loyalty_enabled' => false,
            'auto_upgrade_enabled' => false,
            'auto_downgrade_enabled' => true,
            'visible_in_mobile_app' => false,
            'allow_discount_stacking' => true,
            'default_currency' => 'USD',
            'settings_version' => 7,
            'metadata' => ['scope' => 'persisted'],
        ]);

        $service = app(LoyaltySettingsService::class);
        $settings = $service->current();

        $this->assertTrue($settings->exists);
        $this->assertFalse($settings->loyalty_enabled);
        $this->assertFalse($settings->auto_upgrade_enabled);
        $this->assertTrue($settings->auto_downgrade_enabled);
        $this->assertFalse($settings->visible_in_mobile_app);
        $this->assertTrue($settings->allow_discount_stacking);
        $this->assertSame('USD', $settings->default_currency);
        $this->assertSame(7, $settings->settings_version);
    }

    public function test_version_resolves_correctly(): void
    {
        LoyaltySetting::query()->create([
            'settings_version' => 12,
        ]);

        $service = app(LoyaltySettingsService::class);

        $this->assertSame(12, $service->settingsVersion());
    }
}