<?php

namespace Tests\Feature;

use App\Models\LoyaltySetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoyaltySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_and_update_loyalty_settings_via_api(): void
    {
        $admin = $this->createAdminWithRole('super_admin');

        $loginResponse = $this->postJson(route('api.v1.admin.auth.login'), [
            'login' => $admin->email,
            'password' => 'password',
            'device_name' => 'postman',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson(route('api.v1.admin.loyalty.settings.show'))
            ->assertOk()
            ->assertJsonPath('data.settings.default_currency', 'LYD');

        $this->withToken($token)
            ->putJson(route('api.v1.admin.loyalty.settings.update'), [
                'loyalty_enabled' => false,
                'auto_upgrade_enabled' => false,
                'auto_downgrade_enabled' => true,
                'visible_in_mobile_app' => false,
                'allow_discount_stacking' => true,
                'default_currency' => 'usd',
                'max_global_discount_amount' => 150,
                'minimum_discountable_order_amount' => 500,
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.default_currency', 'USD')
            ->assertJsonPath('data.settings.allow_discount_stacking', true)
            ->assertJsonPath('data.settings.settings_version', 2);

        $this->assertDatabaseHas('loyalty_settings', [
            'default_currency' => 'USD',
            'loyalty_enabled' => false,
            'auto_upgrade_enabled' => false,
            'auto_downgrade_enabled' => true,
            'visible_in_mobile_app' => false,
            'allow_discount_stacking' => true,
            'settings_version' => 2,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_loyalty_manager_cannot_update_loyalty_settings_via_api(): void
    {
        $admin = $this->createAdminWithRole('loyalty_manager');

        $token = $this->postJson(route('api.v1.admin.auth.login'), [
            'login' => $admin->email,
            'password' => 'password',
            'device_name' => 'postman',
        ])->json('data.token');

        $this->withToken($token)
            ->putJson(route('api.v1.admin.loyalty.settings.update'), [
                'default_currency' => 'LYD',
            ])
            ->assertForbidden();
    }

    public function test_customer_cannot_login_to_admin_api(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->postJson(route('api.v1.admin.auth.login'), [
            'login' => $customer->email,
            'password' => 'password',
            'device_name' => 'postman',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['login'],
            ]);
    }

    private function createAdminWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $admin->refresh()->syncRolesByName([$role]);

        LoyaltySetting::query()->create([
            'settings_version' => 1,
            'default_currency' => 'LYD',
        ]);

        return $admin;
    }
}