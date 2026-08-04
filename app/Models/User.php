<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Rbac\RbacRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'full_name',
    'email',
    'phone',
    'country',
    'avatar_path',
    'password',
    'is_admin',
    'account_type',
    'is_active',
    'last_login_at',
    'two_factor_secret',
    'two_factor_confirmed_at',
    'email_verified_at',
    'phone_verified_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'avatar_path'])]
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
            'phone_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        $path = \Illuminate\Support\Facades\Storage::disk('public')->url((string) $this->avatar_path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url($path);
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
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
     * Resolve the default post-authentication route for the user.
     */
    public function homeRouteName(): string
    {
        return $this->isAdminAccount()
            ? 'admin.dashboard'
            : 'customer.support.chat';
    }

    /**
     * Get the roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Get the permissions assigned directly to the user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    /**
     * Get the support tickets opened by the user.
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Customer wallet accounts owned by the user.
     */
    public function customerWallets(): HasMany
    {
        return $this->hasMany(CustomerWallet::class);
    }

    /**
     * Get the support tickets assigned to the user.
     */
    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    /**
     * Get the support messages authored by the user.
     */
    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    /**
     * Get the support ticket history entries triggered by the user.
     */
    public function supportTicketHistories(): HasMany
    {
        return $this->hasMany(SupportTicketHistory::class);
    }

    /**
     * Get notification delivery logs for the user.
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)
            ->latest('id');
    }

    /**
     * Get in-app notifications for the user.
     */
    public function notificationsCenter(): HasMany
    {
        return $this->hasMany(UserNotification::class)
            ->latest('id');
    }

    /**
     * Get registered notification devices for the user.
     */
    public function notificationDevices(): HasMany
    {
        return $this->hasMany(UserNotificationDevice::class)
            ->latest('id');
    }

    /**
     * Get the notification preferences for the user.
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class)
            ->latest('id');
    }

    /**
     * Get the loyalty profile for the user.
     */
    public function loyaltyProfile(): HasMany
    {
        return $this->hasMany(UserLoyaltyProfile::class)
            ->latest('id');
    }

    /**
     * Get the loyalty history entries for the user.
     */
    public function loyaltyHistory(): HasMany
    {
        return $this->hasMany(LoyaltyHistory::class)
            ->latest('changed_at')
            ->latest('id');
    }

    /**
     * Get the orders owned by the customer account.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id')
            ->latest('id');
    }

    /**
     * Get the saved passengers owned by the user.
     */
    public function savedPassengers(): HasMany
    {
        return $this->hasMany(SavedPassenger::class)
            ->latest('created_at');
    }

    /**
     * Get the saved vehicles owned by the user.
     */
    public function savedVehicles(): HasMany
    {
        return $this->hasMany(SavedVehicle::class)
            ->latest('created_at');
    }

    /**
     * Get the saved addresses owned by the user.
     */
    public function savedAddresses(): HasMany
    {
        return $this->hasMany(SavedAddress::class)
            ->orderByDesc('is_default')
            ->latest('updated_at');
    }

    /**
     * Get the favorites owned by the user.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class)
            ->latest('created_at');
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

        if ($this->hasDirectPermissions()) {
            if ($this->relationLoaded('permissions')) {
                return $this->permissions->pluck('name')->contains($permission);
            }

            return $this->permissions()->where('name', $permission)->exists();
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
     * Sync the user permissions by their names.
     *
     * @param  array<int, string>  $permissionNames
     */
    public function syncPermissionsByName(array $permissionNames): void
    {
        if (! $this->hasPermissionUserTable()) {
            return;
        }

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->permissions()->sync($permissionIds);
        $this->unsetRelation('permissions');
    }

    /**
     * Determine whether the user has direct permission assignments.
     */
    public function hasDirectPermissions(): bool
    {
        if (! $this->hasPermissionUserTable()) {
            return false;
        }

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->isNotEmpty();
        }

        return $this->permissions()->exists();
    }

    /**
     * Get the permission names assigned directly to the user.
     *
     * @return array<int, string>
     */
    public function directPermissionNames(): array
    {
        if (! $this->hasPermissionUserTable()) {
            return [];
        }

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->pluck('name')->values()->all();
        }

        return $this->permissions()->pluck('name')->all();
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

        if ($this->hasDirectPermissions()) {
            return $this->directPermissionNames();
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

    /**
     * Determine whether direct user-permission assignments are supported.
     */
    private function hasPermissionUserTable(): bool
    {
        return $this->hasRbacTables() && Schema::hasTable('permission_user');
    }
}
