<?php

namespace App\Providers;

use App\Models\Favorite;
use App\Models\FinancialTransaction;
use App\Models\HotelReview;
use App\Models\LoyaltyHistory;
use App\Models\Order;
use App\Models\SavedAddress;
use App\Models\SavedPassenger;
use App\Models\SavedVehicle;
use App\Models\User;
use App\Modules\Loyalty\Pricing\LoyaltyPricingProvider;
use App\Modules\Pricing\Services\OrderPricingService;
use App\Modules\Pricing\Services\PricingEngine;
use App\Modules\Pricing\Services\PricingPreviewService;
use App\Modules\Pricing\Services\PricingSnapshotFactory;
use App\Modules\Pricing\Services\PricingVersionService;
use App\Observers\FinancialTransactionObserver;
use App\Observers\LoyaltyHistoryObserver;
use App\Policies\FavoritePolicy;
use App\Policies\HotelReviewPolicy;
use App\Policies\OrderPolicy;
use App\Policies\SavedAddressPolicy;
use App\Policies\SavedPassengerPolicy;
use App\Policies\SavedVehiclePolicy;
use App\Support\Rbac\RbacRegistry;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingSnapshotFactory::class);
        $this->app->singleton(PricingVersionService::class);
        $this->app->singleton(LoyaltyPricingProvider::class);

        $this->app->singleton(PricingEngine::class, function ($app): PricingEngine {
            return new PricingEngine(
                $app->make(PricingVersionService::class),
                $app->make(PricingSnapshotFactory::class),
                [
                    $app->make(LoyaltyPricingProvider::class),
                ],
            );
        });

        $this->app->singleton(OrderPricingService::class, function ($app): OrderPricingService {
            return new OrderPricingService(
                $app->make(PricingEngine::class),
            );
        });

        $this->app->singleton(PricingPreviewService::class, function ($app): PricingPreviewService {
            return new PricingPreviewService(
                $app->make(PricingEngine::class),
                $app->make(PricingVersionService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Vite::prefetch(concurrency: 3);

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Favorite::class, FavoritePolicy::class);
        Gate::policy(SavedPassenger::class, SavedPassengerPolicy::class);
        Gate::policy(SavedVehicle::class, SavedVehiclePolicy::class);
        Gate::policy(SavedAddress::class, SavedAddressPolicy::class);
        Gate::policy(HotelReview::class, HotelReviewPolicy::class);
        FinancialTransaction::observe(FinancialTransactionObserver::class);
        LoyaltyHistory::observe(LoyaltyHistoryObserver::class);

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

        foreach ([
            'support.view-order-actions' => 'orders.change-status',
            'support.cancel-order' => 'orders.change-status',
            'support.full-refund' => 'orders.change-status',
            'support.partial-refund' => 'orders.change-status',
            'finance.reverse-refund' => 'orders.change-status',
        ] as $ability => $legacyFallback) {
            Gate::define($ability, fn (User $user): bool => $user->hasPermissionTo($ability) || $user->hasPermissionTo($legacyFallback));
        }

        try {
            $settings = app(SystemSettingsService::class);
            config([
                'mail.from.name' => $settings->mailFromName(),
            ]);

            $support = $settings->supportEmail();
            if ($support !== '') {
                Mail::alwaysReplyTo($support, $settings->companyName());
            }
        } catch (Throwable) {
            // Settings table may not exist during early migrate/install.
        }
    }
}
