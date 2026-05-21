<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltySettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $this->actingAs($admin)
            ->putJson(route('admin.loyalty.settings.update'), [
                'default_currency' => 'LYD',
            ])
            ->assertOk();
    }

    public function test_loyalty_manager_forbidden(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['loyalty_manager']);

        $this->actingAs($admin)
            ->putJson(route('admin.loyalty.settings.update'), [
                'default_currency' => 'LYD',
            ])
            ->assertForbidden();
    }

    public function test_normal_admin_forbidden(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['admin']);

        $this->actingAs($admin)
            ->putJson(route('admin.loyalty.settings.update'), [
                'default_currency' => 'LYD',
            ])
            ->assertForbidden();
    }
}