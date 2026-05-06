<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Rbac\RbacRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'full_name',
    'email',
    'phone',
    'country',
    'password',
    'is_admin',
    'account_type',
    'is_active',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ACCOUNT_TYPE_CUSTOMER = 'customer';

    public const ACCOUNT_TYPE_ADMIN = 'admin';

    private static ?bool $rbacTablesExist = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine whether the user is an administrative account.
     */
    public function isAdminAccount(): bool
    {
        return $this->account_type === self::ACCOUNT_TYPE_ADMIN;
    }

    /**
     * Determine whether the user is a customer account.
     */
    public function isCustomerAccount(): bool
    {
        return $this->account_type === self::ACCOUNT_TYPE_CUSTOMER;
    }

    /**
     * Get the roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Determine whether the user can access the admin area.
     */
    public function canAccessAdminPanel(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->isAdminAccount()) {
            return false;
        }

        if (! $this->hasRbacTables()) {
            return (bool) $this->is_admin;
        }

        return $this->roles()->exists();
    }

    /**
     * Determine whether the user has one of the given roles.
     *
     * @param  array<int, string>|string  $roles
     */
    public function hasRole(array|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        if ($roles === []) {
            return false;
        }

        if (! $this->hasRbacTables()) {
            return $this->isAdminAccount()
                && $this->is_admin
                && in_array(RbacRegistry::ROLE_SUPER_ADMIN, $roles, true);
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Determine whether the user has the given permission.
     */
    public function hasPermissionTo(string $permission): bool
    {
        if (! $this->hasRbacTables()) {
            return $this->isAdminAccount() && (bool) $this->is_admin;
        }

        if ($this->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return true;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles
                ->loadMissing('permissions')
                ->flatMap(fn (Role $role) => $role->permissions)
                ->pluck('name')
                ->contains($permission);
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($permission): void {
            $query->where('name', $permission);
        })->exists();
    }

    /**
     * Sync the user roles by their names.
     *
     * @param  array<int, string>  $roleNames
     */
    public function syncRolesByName(array $roleNames): void
    {
        if (! $this->hasRbacTables()) {
            $this->forceFill([
                'is_admin' => $roleNames !== [],
                'account_type' => $roleNames !== []
                    ? self::ACCOUNT_TYPE_ADMIN
                    : self::ACCOUNT_TYPE_CUSTOMER,
            ])->save();

            return;
        }

        $roleIds = Role::query()
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->all();

        $this->roles()->sync($roleIds);

        $this->forceFill([
            'is_admin' => $roleIds !== [],
            'account_type' => $roleIds !== []
                ? self::ACCOUNT_TYPE_ADMIN
                : self::ACCOUNT_TYPE_CUSTOMER,
        ])->save();

        $this->unsetRelation('roles');
    }

    /**
     * Get the primary role used by the admin UI.
     */
    public function primaryRole(): ?Role
    {
        if (! $this->hasRbacTables()) {
            return null;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->sortBy('id')->first();
        }

        return $this->roles()->orderBy('roles.id')->first();
    }

    /**
     * Get the role names assigned to the user.
     *
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        if (! $this->hasRbacTables()) {
            return $this->isAdminAccount() && $this->is_admin
                ? [RbacRegistry::ROLE_SUPER_ADMIN]
                : [];
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('name')->values()->all();
        }

        return $this->roles()->pluck('name')->all();
    }

    /**
     * Get the permission names resolved through roles.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        if (! $this->hasRbacTables()) {
            return $this->isAdminAccount() && $this->is_admin
                ? RbacRegistry::permissionNames()
                : [];
        }

        if ($this->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return RbacRegistry::permissionNames();
        }

        $roles = $this->relationLoaded('roles')
            ? $this->roles->loadMissing('permissions')
            : $this->roles()->with('permissions')->get();

        return $roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Determine whether the RBAC tables exist in the current database.
     */
    private function hasRbacTables(): bool
    {
        if (self::$rbacTablesExist !== null) {
            return self::$rbacTablesExist;
        }

        return self::$rbacTablesExist = Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('role_user')
            && Schema::hasTable('permission_role');
    }
}
