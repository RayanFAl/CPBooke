<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_delete_team_member(): void
    {
        $actor = $this->superAdmin();
        $target = $this->teamMember('retaj@booke.local');

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.team.index'))
            ->assertSessionHas('success', 'Team member deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_customer_accounts_cannot_be_deleted_from_team_destroy_route(): void
    {
        $actor = $this->superAdmin();
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $customer))
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_team_member_cannot_delete_their_own_account(): void
    {
        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->from(route('admin.team.show', $actor))
            ->delete(route('admin.users.destroy', $actor))
            ->assertRedirect(route('admin.team.show', $actor))
            ->assertSessionHas('error', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_last_active_super_admin_cannot_be_deleted(): void
    {
        $actor = $this->superAdmin();

        User::query()
            ->whereKeyNot($actor->id)
            ->whereHas('roles', fn ($query) => $query->where('name', RbacRegistry::ROLE_SUPER_ADMIN))
            ->delete();

        $this->actingAs($actor)
            ->from(route('admin.team.show', $actor))
            ->delete(route('admin.users.destroy', $actor))
            ->assertRedirect(route('admin.team.show', $actor))
            ->assertSessionHas('error', 'You cannot delete your own account.');
    }

    public function test_non_super_admin_cannot_delete_super_admin_team_member(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);
        $actor->syncPermissionsByName(['users.view', 'users.update']);

        $target = $this->superAdmin('other-super@booke.local');

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    private function superAdmin(string $email = 'super@booke.local'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $user->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        return $user->fresh(['roles', 'permissions']);
    }

    private function teamMember(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $user->syncRolesByName([RbacRegistry::ROLE_SUPPORT_AGENT]);

        return $user->fresh(['roles', 'permissions']);
    }
}
