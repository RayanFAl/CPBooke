<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'admin'])
    ->as('admin.')
    ->group(function (): void {
        Route::redirect('/', '/admin/dashboard')->name('home');

        require base_path('routes/admin/dashboard.php');
        require base_path('routes/admin/users.php');
        require base_path('routes/admin/orders.php');
        require base_path('routes/admin/finance.php');
        require base_path('routes/admin/provider-wallets.php');
        require base_path('routes/admin/customer-wallets.php');
        require base_path('routes/admin/suppliers.php');
        require base_path('routes/admin/approvals.php');
        require base_path('routes/admin/settlements.php');
        require base_path('routes/admin/provider-health.php');
        require base_path('routes/admin/monitoring.php');
        require base_path('routes/admin/audit.php');
        require base_path('routes/admin/search.php');
        require base_path('routes/admin/governance.php');
        require base_path('routes/admin/loyalty.php');
        require base_path('routes/admin/notifications.php');
        require base_path('routes/admin/support.php');
        require base_path('routes/admin/settings.php');
        require base_path('routes/admin/ai.php');
        require base_path('routes/admin/home.php');
        require base_path('routes/admin/content.php');
        require base_path('routes/admin/airports.php');
        require base_path('routes/admin/mobile-app.php');
    });
