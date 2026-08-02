<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_non_super_admin_cannot_change_own_role_or_permissions(): void
    {
        $actor = $this->adminWithPermissions([
            'users.view',
            'users.create',
            'users.update',
            'orders.view',
            'support.view',
            'search.view',
        ]);

        $this->actingAs($actor)
            ->from(route('admin.users.edit', $actor))
            ->put(route('admin.users.update', $actor), [
                'full_name' => $actor->full_name ?: $actor->name,
                'email' => $actor->email,
                'phone' => $actor->phone,
                'country' => $actor->country,
                'role' => RbacRegistry::ROLE_TEAM_MEMBER,
                'permissions' => [
                    'users.view',
                    'orders.view',
                    'support.view',
                    'search.view',
                ],
            ])
            ->assertForbidden();

        $actor->refresh();
        $this->assertTrue($actor->hasRole(RbacRegistry::ROLE_ADMIN));
        $this->assertFalse($actor->hasRole(RbacRegistry::ROLE_TEAM_MEMBER));
    }

    public function test_non_super_admin_cannot_assign_equal_or_higher_role(): void
    {
        $actor = $this->adminWithPermissions([
            'users.view',
            'users.create',
            'users.update',
            'orders.view',
            'support.view',
            'search.view',
            'finance.view',
            'approvals.view',
            'approvals.approve',
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
                'full_name' => $target->full_name ?: $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'country' => $target->country,
                'role' => RbacRegistry::ROLE_ADMIN,
                'permissions' => [
                    'users.view',
                    'orders.view',
                    'support.view',
                ],
            ])
            ->assertSessionHasErrors('role');

        $target->refresh();
        $this->assertTrue($target->hasRole(RbacRegistry::ROLE_TEAM_MEMBER));
        $this->assertFalse($target->hasRole(RbacRegistry::ROLE_ADMIN));
    }

    public function test_non_super_admin_cannot_manage_peer_or_higher_users(): void
    {
        $actor = $this->adminWithPermissions([
            'users.view',
            'users.create',
            'users.update',
            'orders.view',
            'support.view',
            'search.view',
        ]);

        $peer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $peer->syncRolesByName([RbacRegistry::ROLE_ADMIN]);

        $this->actingAs($actor)
            ->get(route('admin.users.edit', $peer))
            ->assertForbidden();
    }

    public function test_admin_cannot_assign_permissions_they_lack(): void
    {
        $actor = $this->adminWithPermissions([
            'users.view',
            'users.create',
            'users.update',
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

        try {
            $this->withoutExceptionHandling()
                ->actingAs($actor)
                ->put(route('admin.users.update', $target), [
                    'full_name' => $target->full_name ?: $target->name,
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
                ]);

            $this->fail('Expected authorization failure when assigning foreign permissions.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $target->refresh();
        $this->assertTrue($target->hasRole(RbacRegistry::ROLE_TEAM_MEMBER));
        $this->assertFalse($target->hasPermissionTo('finance.view'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function adminWithPermissions(array $permissions): User
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);
        $actor->syncPermissionsByName($permissions);

        return $actor->fresh(['roles', 'permissions']);
    }
}
