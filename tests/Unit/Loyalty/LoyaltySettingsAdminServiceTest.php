<?php

namespace Tests\Unit\Loyalty;

use App\Models\LoyaltySetting;
use App\Models\User;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Modules\Admin\Loyalty\Services\LoyaltySettingsAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LoyaltySettingsAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_settings_when_missing(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $request = Mockery::mock(UpdateLoyaltySettingsRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'default_currency' => 'USD',
            'loyalty_enabled' => false,
        ]);

        $settings = app(LoyaltySettingsAdminService::class)->update($request, $admin);

        $this->assertTrue($settings->exists);
        $this->assertSame('USD', $settings->default_currency);
        $this->assertFalse($settings->loyalty_enabled);
        $this->assertSame(2, $settings->settings_version);
        $this->assertSame($admin->id, $settings->updated_by_user_id);
    }

    public function test_updates_persisted_settings(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        LoyaltySetting::query()->create([
            'default_currency' => 'LYD',
            'settings_version' => 5,
        ]);

        $request = Mockery::mock(UpdateLoyaltySettingsRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'default_currency' => 'EUR',
            'allow_discount_stacking' => true,
        ]);

        $settings = app(LoyaltySettingsAdminService::class)->update($request, $admin);

        $this->assertSame('EUR', $settings->default_currency);
        $this->assertTrue($settings->allow_discount_stacking);
    }

    public function test_increments_version_and_updates_updated_by_user_id(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        LoyaltySetting::query()->create([
            'settings_version' => 9,
        ]);

        $request = Mockery::mock(UpdateLoyaltySettingsRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'default_currency' => 'LYD',
        ]);

        $settings = app(LoyaltySettingsAdminService::class)->update($request, $admin);

        $this->assertSame(10, $settings->settings_version);
        $this->assertSame($admin->id, $settings->updated_by_user_id);
    }
}