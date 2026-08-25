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
            ->assertRedirect(route('admin.team.show', $target));

        $target->refresh();

        $this->assertTrue($target->hasPermissionTo('users.view'));
        $this->assertTrue($target->hasPermissionTo('support.view'));
        $this->assertFalse($target->hasPermissionTo('orders.view'));
    }

    public function test_non_super_admin_cannot_assign_permissions_they_do_not_have(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);
        $actor->syncPermissionsByName([
            'users.view',
            'users.update',
            'users.create',
            'orders.view',
            'support.view',
            'search.view',
        ]);

        $target = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $target->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);
        $target->syncPermissionsByName([
            'users.view',
            'orders.view',
            'support.view',
            'search.view',
        ]);

        $this->actingAs($actor)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.update', $target), [
                'full_name' => $target->full_name,
                'email' => $target->email,
                'phone' => $target->phone,
                'country' => $target->country,
                'role' => RbacRegistry::ROLE_TEAM_MEMBER,
                'permissions' => [
                    'users.view',
                    'orders.view',
                    'support.view',
                    'search.view',
                    'finance.view',
                ],
            ])
            ->assertForbidden();

        $target->refresh();
        $this->assertFalse($target->hasPermissionTo('finance.view'));
    }

    public function test_non_super_admin_cannot_assign_settings_manage(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);
        $actor->syncPermissionsByName([
            'users.view',
            'users.update',
            'users.create',
            'settings.manage',
            'orders.view',
            'support.view',
            'search.view',
        ]);

        $target = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $target->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);
        $target->syncPermissionsByName([
            'users.view',
            'orders.view',
            'support.view',
            'search.view',
        ]);

        $this->actingAs($actor)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.update', $target), [
                'full_name' => $target->full_name,
                'email' => $target->email,
                'phone' => $target->phone,
                'country' => $target->country,
                'role' => RbacRegistry::ROLE_TEAM_MEMBER,
                'permissions' => [
                    'users.view',
                    'orders.view',
                    'support.view',
                    'search.view',
                    'settings.manage',
                ],
            ])
            ->assertForbidden();

        $target->refresh();
        $this->assertFalse($target->hasPermissionTo('settings.manage'));
    }
}
