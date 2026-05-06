<?php

namespace App\Modules\Admin\Users\Services;

use App\Models\User;
use App\Modules\Admin\Access\Services\AccessControlService;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
    ) {
    }

    /**
     * Build the paginated admin users listing.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(User $actor, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->select([
                'id',
                'name',
                'full_name',
                'email',
                'phone',
                'country',
                'account_type',
                'is_admin',
                'is_active',
                'last_login_at',
                'created_at',
            ])
            ->when(! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN), function ($query): void {
                $query->whereDoesntHave('roles', function ($roleQuery): void {
                    $roleQuery->where('name', RbacRegistry::ROLE_SUPER_ADMIN);
                });
            })
            ->when($filters['name'] ?? null, function ($query, $name): void {
                $query->where(function ($nestedQuery) use ($name): void {
                    $nestedQuery
                        ->where('full_name', 'like', "%{$name}%")
                        ->orWhere('name', 'like', "%{$name}%");
                });
            })
            ->when($filters['email'] ?? null, fn ($query, $email) => $query->where('email', 'like', "%{$email}%"))
            ->when($filters['phone'] ?? null, fn ($query, $phone) => $query->where('phone', 'like', "%{$phone}%"))
            ->when($filters['account_type'] ?? null, fn ($query, $accountType) => $query->where('account_type', $accountType))
            ->when($filters['role'] ?? null, function ($query, $role): void {
                if ($role === 'unassigned') {
                    $query->whereDoesntHave('roles');

                    return;
                }

                $query->whereHas('roles', function ($roleQuery) use ($role): void {
                    $roleQuery->where('name', $role);
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => $this->summaryPayload($user));
    }

    /**
     * Build the detailed payload for a user profile.
     *
     * @return array<string, mixed>
     */
    public function detailPayload(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'is_active' => (bool) $user->is_active,
            'is_admin' => (bool) $user->is_admin,
            'role' => $this->rolePayload($user),
            'permissions' => $user->permissionNames(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build the edit payload for a user profile.
     *
     * @return array<string, mixed>
     */
    public function editPayload(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'is_active' => (bool) $user->is_active,
            'is_admin' => (bool) $user->is_admin,
            'role' => $user->primaryRole()?->name,
        ];
    }

    /**
     * Create a new administrative user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $roleName = (string) $data['role'];

        $this->accessControlService->assertCanAssignRole($actor, $roleName);

        return DB::transaction(function () use ($data, $roleName): User {
            $user = User::query()->create([
                'name' => $data['full_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'country' => $data['country'] ?: null,
                'password' => $data['password'],
                'is_admin' => true,
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'is_active' => true,
            ]);

            $user->syncRolesByName([$roleName]);

            return $user->load('roles');
        });
    }

    /**
     * Update the specified user.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->accessControlService->assertCanManageUser($actor, $user);

        $currentRoleName = $user->primaryRole()?->name;
        $nextRoleName = (string) $data['role'];

        $this->accessControlService->assertCanAssignRole($actor, $nextRoleName);

        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)
            && $currentRoleName !== $nextRoleName
            && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'The last active super admin must keep the super admin role.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $nextRoleName): User {
            $user->fill([
                'name' => $data['full_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'country' => $data['country'] ?: null,
            ])->save();

            $user->syncRolesByName([$nextRoleName]);

            return $user->refresh()->load('roles');
        });
    }

    /**
     * Toggle the specified user's active status.
     *
     * @return array{updated: bool, message: string}
     */
    public function toggleStatus(User $actor, User $user): array
    {
        $this->accessControlService->assertCanManageUser($actor, $user);

        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN) && $user->is_active && $this->isLastActiveSuperAdmin($user)) {
            return [
                'updated' => false,
                'message' => 'The last active super admin cannot be deactivated.',
            ];
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return [
            'updated' => true,
            'message' => $user->is_active
                ? 'User account activated successfully.'
                : 'User account deactivated successfully.',
        ];
    }

    /**
     * Build user-profile placeholder datasets until related modules exist.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function profilePlaceholders(): array
    {
        return [
            'orders' => [
                ['title' => 'Recent orders feed', 'description' => 'Order history integration will be attached from the Orders module.'],
                ['title' => 'Lifecycle timeline', 'description' => 'Booking status changes and service milestones will appear here.'],
            ],
            'payments' => [
                ['title' => 'Payment activity', 'description' => 'Payment records, refunds, and balance summaries are reserved for Finance integration.'],
                ['title' => 'Risk indicators', 'description' => 'Chargeback and reconciliation indicators can be injected here later.'],
            ],
            'tickets' => [
                ['title' => 'Support threads', 'description' => 'Open and resolved support interactions will appear here once the Support module is connected.'],
                ['title' => 'Escalation context', 'description' => 'Priority, ownership, and SLA-related data will be surfaced in this section.'],
            ],
        ];
    }

    /**
     * Build the summary payload for table listings.
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'role' => $this->rolePayload($user),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build the role payload for the user.
     */
    private function rolePayload(User $user): ?array
    {
        $role = $user->primaryRole();

        if (! $role) {
            return null;
        }

        return [
            'name' => $role->name,
            'label' => $role->label,
        ];
    }

    /**
     * Determine whether the specified user is the last active super admin.
     */
    private function isLastActiveSuperAdmin(User $user): bool
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', RbacRegistry::ROLE_SUPER_ADMIN);
            })
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}