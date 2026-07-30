<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_user_with_custom_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $this->actingAs($actor)
            ->post(route('admin.users.store'), [
                'full_name' => 'Custom Support User',
                'email' => 'custom-support@booke.local',
                'phone' => null,
                'country' => null,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => RbacRegistry::ROLE_SUPPORT_AGENT,
                'permissions' => [
                    'orders.view',
                    'support.view',
                ],
            ])
            ->assertRedirect();

        $createdUser = User::query()->where('email', 'custom-support@booke.local')->firstOrFail();
        $createdUser->refresh();

        $this->assertTrue($createdUser->hasRole(RbacRegistry::ROLE_SUPPORT_AGENT));
        $this->assertTrue($createdUser->hasPermissionTo('orders.view'));
        $this->assertTrue($createdUser->hasPermissionTo('support.view'));
        $this->assertFalse($createdUser->hasPermissionTo('support.cancel-order'));
        $this->assertFalse($createdUser->hasPermissionTo('finance.view'));
    }

    public function test_super_admin_can_update_user_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $target = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $target->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);
        $target->syncPermissionsByName([
            'users.view',
            'orders.view',
            'support.view',
        ]);

        $this->actingAs($actor)
            ->put(route('admin.users.update', $target), [
                'full_name' => $target->full_name,
                'email' => $target->email,
                'phone' => $target->phone,
                'country' => $target->country,
                'role' => RbacRegistry::ROLE_TEAM_MEMBER,
                'permissions' => [
                    'users.view',
                    'support.view',
                ],
            ])
            ->assertRedirect(route('admin.users.show', $target));

        $target->refresh();

        $this->assertTrue($target->hasPermissionTo('users.view'));
        $this->assertTrue($target->hasPermissionTo('support.view'));
        $this->assertFalse($target->hasPermissionTo('orders.view'));
    }
}
