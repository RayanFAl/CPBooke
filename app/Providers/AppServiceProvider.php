<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Vite::prefetch(concurrency: 3);

        Gate::policy(Order::class, OrderPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            if (in_array($ability, RbacRegistry::permissionNames(), true)
                && $user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
                return true;
            }

            return null;
        });

        Gate::define('access-admin', fn (User $user): bool => $user->canAccessAdminPanel());

        foreach (RbacRegistry::permissionNames() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermissionTo($permission));
        }
    }
}
