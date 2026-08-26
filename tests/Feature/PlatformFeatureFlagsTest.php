<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Settings\Services\SystemSettingsService;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformFeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::forget(SystemSettingsService::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedSettings(array $overrides = []): void
    {
        SystemSetting::query()->create(array_merge([
            'company_name' => 'CPBooke',
            'default_currency' => 'LYD',
            'feature_legacy_order_create' => false,
            'feature_chat_enabled' => true,
            'feature_maintenance_mode' => false,
            'settings_version' => 1,
        ], $overrides));

        Cache::forget(SystemSettingsService::CACHE_KEY);
    }

    public function test_legacy_order_create_is_blocked_when_flag_disabled(): void
    {
        $this->seedSettings(['feature_legacy_order_create' => false]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders', [
            'provider_name' => 'Test',
            'currency' => 'LYD',
            'total_amount' => '10.00',
            'service_type' => 'flight',
        ])->assertForbidden()
            ->assertJsonPath('code', 'legacy_order_create_disabled');
    }

    public function test_support_chat_api_is_blocked_when_flag_disabled(): void
    {
        $this->seedSettings(['feature_chat_enabled' => false]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/support/chat/tickets')
            ->assertForbidden()
            ->assertJsonPath('code', 'support_chat_disabled');
    }

    public function test_maintenance_mode_blocks_customer_api_but_allows_admin(): void
    {
        $this->seedSettings(['feature_maintenance_mode' => true]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/home/content')
            ->assertStatus(503)
            ->assertJsonPath('code', 'maintenance_mode');

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $admin->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/home/content')->assertOk();
    }
}
