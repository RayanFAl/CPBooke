<?php

namespace Tests\Feature;

use App\Models\LoyaltySetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_defaults_when_missing(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->getJson(route('admin.loyalty.settings.show'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loyalty_enabled', true)
            ->assertJsonPath('data.auto_upgrade_enabled', true)
            ->assertJsonPath('data.auto_downgrade_enabled', false)
            ->assertJsonPath('data.visible_in_mobile_app', true)
            ->assertJsonPath('data.allow_discount_stacking', false)
            ->assertJsonPath('data.default_currency', 'LYD')
            ->assertJsonPath('data.max_global_discount_amount', null)
            ->assertJsonPath('data.minimum_discountable_order_amount', null)
            ->assertJsonPath('data.settings_version', 1);
    }

    public function test_update_persists_values(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->putJson(route('admin.loyalty.settings.update'), [
                'loyalty_enabled' => false,
                'auto_upgrade_enabled' => false,
                'auto_downgrade_enabled' => true,
                'visible_in_mobile_app' => false,
                'allow_discount_stacking' => true,
                'default_currency' => 'usd',
                'max_global_discount_amount' => 150,
                'minimum_discountable_order_amount' => 500,
                'metadata' => ['source' => 'test'],
            ])
            ->assertOk()
            ->assertJsonPath('data.default_currency', 'USD')
            ->assertJsonPath('data.settings_version', 2);

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

    public function test_response_shape_stable(): void
    {
        $admin = $this->superAdmin();

        LoyaltySetting::query()->create([
            'default_currency' => 'LYD',
            'settings_version' => 3,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.loyalty.settings.show'))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'loyalty_enabled',
                    'auto_upgrade_enabled',
                    'auto_downgrade_enabled',
                    'visible_in_mobile_app',
                    'allow_discount_stacking',
                    'default_currency',
                    'max_global_discount_amount',
                    'minimum_discountable_order_amount',
                    'settings_version',
                    'updated_at',
                ],
            ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        return $admin;
    }
}