<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's RBAC foundation.
     */
    public function run(): void
    {
        $roleNames = array_column(RbacRegistry::roles(), 'name');
        $permissionNames = array_column(RbacRegistry::permissions(), 'name');

        Role::query()
            ->whereNotIn('name', $roleNames)
            ->delete();

        Permission::query()
            ->whereNotIn('name', $permissionNames)
            ->delete();

        foreach (RbacRegistry::roles() as $roleData) {
            Role::query()->updateOrCreate(
                ['name' => $roleData['name']],
                $roleData,
            );
        }

        foreach (RbacRegistry::permissions() as $permissionData) {
            Permission::query()->updateOrCreate(
                ['name' => $permissionData['name']],
                $permissionData,
            );
        }

        foreach (RbacRegistry::rolePermissions() as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        User::query()
            ->where('account_type', User::ACCOUNT_TYPE_ADMIN)
            ->whereDoesntHave('roles')
            ->each(function (User $user): void {
                $user->syncRolesByName([RbacRegistry::ROLE_ADMIN]);
            });
    }
}