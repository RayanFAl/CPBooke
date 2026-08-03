<?php

namespace App\Modules\Admin\Access\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class AccessControlService
{
    /**
     * Get the assignable roles for the acting user.
     *
     * @return Collection<int, Role>
     */
    public function availableRolesFor(User $actor): Collection
    {
        return Role::query()
            ->when(! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN), function ($query): void {
                $query->where('name', '!=', RbacRegistry::ROLE_SUPER_ADMIN);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the role options payload for forms and filters.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function availableRoleOptionsFor(User $actor): array
    {
        return $this->availableRolesFor($actor)
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'label' => $role->label,
            ])
            ->values()
            ->all();
    }

    /**
     * Ensure the acting user can manage the subject user.
     */
    public function assertCanManageUser(User $actor, User $subject): void
    {
        if ($subject->hasRole(RbacRegistry::ROLE_SUPER_ADMIN) && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can manage super admin accounts.');
        }
    }

    /**
     * Ensure the acting user can assign the requested role.
     */
    public function assertCanAssignRole(User $actor, string $roleName): void
    {
        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can assign the super admin role.');
        }
    }

    /**
     * Get permission groups for user assignment forms.
     *
     * @return array<int, array{module: string, label: string, permissions: array<int, array{name: string, label: string, description: string}>}>
     */
    public function permissionGroupsFor(User $actor): array
    {
        $permissions = collect(RbacRegistry::permissions());

        if (! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            $allowed = $actor->permissionNames();
            $permissions = $permissions->filter(
                fn (array $permission): bool => in_array($permission['name'], $allowed, true)
            );
        }

        return $permissions
            ->groupBy('module')
            ->map(function (Collection $items, string $module): array {
                return [
                    'module' => $module,
                    'label' => ucfirst(str_replace('_', ' ', $module)),
                    'permissions' => $items
                        ->map(fn (array $permission): array => [
                            'name' => $permission['name'],
                            'label' => $permission['label'],
                            'description' => $permission['description'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get the default permission map used by role templates in the admin UI.
     *
     * @return array<string, array<int, string>>
     */
    public function rolePermissionMap(): array
    {
        return RbacRegistry::rolePermissions();
    }

    /**
     * Ensure the acting user can assign the requested permissions.
     *
     * @param  array<int, string>  $permissionNames
     */
    public function assertCanAssignPermissions(User $actor, array $permissionNames, string $roleName): void
    {
        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN) {
            return;
        }

        if ($permissionNames === []) {
            throw new AuthorizationException('At least one permission must be selected.');
        }

        if (! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            $allowed = $actor->permissionNames();

            foreach ($permissionNames as $permissionName) {
                if (! in_array($permissionName, $allowed, true)) {
                    throw new AuthorizationException('You cannot assign permissions you do not have.');
                }
            }
        }

        if (in_array('settings.manage', $permissionNames, true) && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can assign settings management access.');
        }
    }
}
