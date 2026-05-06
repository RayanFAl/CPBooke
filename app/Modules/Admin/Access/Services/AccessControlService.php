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
}