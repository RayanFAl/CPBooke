<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Orders\Services\OrderCostService;
use App\Modules\Settings\Services\SystemSettingsService;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_settings_manage_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);

        $this->actingAs($actor)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_update_company_and_currency_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $this->actingAs($actor)
            ->put(route('admin.settings.update'), [
                'company_name' => 'Booke Ops',
                'support_email' => 'support@booke.test',
                'default_currency' => 'usd',
                'timezone' => 'UTC',
                'locale' => 'en',
                'default_commission_percent' => 12.5,
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = SystemSetting::query()->firstOrFail();

        $this->assertSame('Booke Ops', $settings->company_name);
        $this->assertSame('support@booke.test', $settings->support_email);
        $this->assertSame('USD', $settings->default_currency);
        $this->assertSame('12.50', (string) $settings->default_commission_percent);
        $this->assertSame(2, (int) $settings->settings_version);

        $this->assertSame('USD', app(SystemSettingsService::class)->defaultCurrency());
    }

    public function test_non_super_admin_cannot_enable_maintenance_mode(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);

        SystemSetting::query()->create(SystemSetting::defaultAttributes());

        $this->actingAs($actor)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'feature_maintenance_mode' => true,
                'default_currency' => 'LYD',
                'timezone' => 'UTC',
                'locale' => 'en',
            ])
            ->assertForbidden();

        $this->assertFalse((bool) SystemSetting::query()->firstOrFail()->feature_maintenance_mode);
    }

    public function test_order_cost_service_uses_platform_default_commission_after_provider(): void
    {
        SystemSetting::query()->create([
            ...SystemSetting::defaultAttributes(),
            'default_commission_percent' => 10,
        ]);

        app(SystemSettingsService::class)->forgetCache();

        $order = new Order([
            'total_amount' => 100,
            'final_amount' => 100,
        ]);

        $resolved = app(OrderCostService::class)->resolve($order, null, []);

        $this->assertSame('10.00', $resolved['commission_amount']);
        $this->assertSame('90.00', $resolved['supplier_cost']);
        $this->assertSame('10.00', $resolved['margin_percent']);
    }

    public function test_provider_commission_wins_over_platform_default(): void
    {
        SystemSetting::query()->create([
            ...SystemSetting::defaultAttributes(),
            'default_commission_percent' => 10,
        ]);

        app(SystemSettingsService::class)->forgetCache();

        $provider = new Provider([
            'commission_rate' => 25,
        ]);

        $order = new Order([
            'total_amount' => 200,
            'final_amount' => 200,
        ]);

        $resolved = app(OrderCostService::class)->resolve($order, $provider, []);

        $this->assertSame('50.00', $resolved['commission_amount']);
        $this->assertSame('150.00', $resolved['supplier_cost']);
    }
}
