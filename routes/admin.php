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
        require base_path('routes/admin/support.php');
        require base_path('routes/admin/settings.php');
    });