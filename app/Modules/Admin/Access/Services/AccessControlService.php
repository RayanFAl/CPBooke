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
     * Relative privilege rank used to prevent lateral/upward role escalation.
     *
     * @var array<string, int>
     */
    private const ROLE_RANKS = [
        RbacRegistry::ROLE_SUPER_ADMIN => 100,
        RbacRegistry::ROLE_ADMIN => 80,
        RbacRegistry::ROLE_OPERATIONS_MANAGER => 70,
        RbacRegistry::ROLE_FINANCE_MANAGER => 60,
        RbacRegistry::ROLE_SUPPORT_AGENT => 50,
        RbacRegistry::ROLE_LOYALTY_MANAGER => 50,
        RbacRegistry::ROLE_TEAM_MEMBER => 40,
        RbacRegistry::ROLE_READ_ONLY_ANALYST => 30,
    ];

    /**
     * Get the assignable roles for the acting user.
     *
     * @return Collection<int, Role>
     */
    public function availableRolesFor(User $actor): Collection
    {
        return Role::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Role $role): bool => $this->canAssignRole($actor, $role->name))
            ->values();
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

        if ($actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return;
        }

        $subjectRole = $subject->primaryRole()?->name;

        if ($subjectRole !== null && $this->roleRank($subjectRole) >= $this->highestRoleRank($actor)) {
            throw new AuthorizationException('You cannot manage users with equal or higher access.');
        }
    }

    /**
     * Ensure the acting user can assign the requested role.
     */
    public function assertCanAssignRole(User $actor, string $roleName, ?User $subject = null): void
    {
        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can assign the super admin role.');
        }

        if ($actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return;
        }

        if ($subject !== null && $actor->is($subject)) {
            throw new AuthorizationException('You cannot change your own role or permissions.');
        }

        if (! $this->canAssignRole($actor, $roleName)) {
            throw new AuthorizationException('You cannot assign a role with equal or higher access.');
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
     * Role permission templates scoped to what the actor may assign.
     *
     * @return array<string, array<int, string>>
     */
    public function assignableRolePermissionMapFor(User $actor): array
    {
        $map = $this->rolePermissionMap();

        if ($actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return $map;
        }

        $allowedRoles = $this->availableRolesFor($actor)->pluck('name')->all();
        $allowedPermissions = $actor->permissionNames();

        $scoped = [];

        foreach ($map as $roleName => $permissions) {
            if (! in_array($roleName, $allowedRoles, true)) {
                continue;
            }

            $scoped[$roleName] = array_values(array_intersect($permissions, $allowedPermissions));
        }

        return $scoped;
    }

    /**
     * Ensure the acting user can assign the requested permissions.
     *
     * @param  array<int, string>  $permissionNames
     */
    public function assertCanAssignPermissions(User $actor, array $permissionNames, string $roleName, ?User $subject = null): void
    {
        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN) {
            return;
        }

        if ($permissionNames === []) {
            throw new AuthorizationException('At least one permission must be selected.');
        }

        if (! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN) && $subject !== null && $actor->is($subject)) {
            throw new AuthorizationException('You cannot change your own role or permissions.');
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

        if (in_array('partners.manage', $permissionNames, true) && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can assign partner management access.');
        }
    }

    public function roleRank(string $roleName): int
    {
        return self::ROLE_RANKS[$roleName] ?? 0;
    }

    public function highestRoleRank(User $actor): int
    {
        $rank = 0;

        foreach ($actor->roles as $role) {
            $rank = max($rank, $this->roleRank($role->name));
        }

        return $rank;
    }

    public function canAssignRole(User $actor, string $roleName): bool
    {
        if ($actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return true;
        }

        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN) {
            return false;
        }

        if ($this->roleRank($roleName) >= $this->highestRoleRank($actor)) {
            return false;
        }

        $rolePermissions = RbacRegistry::rolePermissions()[$roleName] ?? [];
        $allowed = $actor->permissionNames();

        foreach ($rolePermissions as $permission) {
            if (! in_array($permission, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
