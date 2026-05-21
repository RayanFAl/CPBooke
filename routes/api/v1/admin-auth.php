<?php

use App\Modules\Api\Admin\Auth\Http\Controllers\AdminAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/auth')
    ->as('admin.auth.')
    ->controller(AdminAuthController::class)
    ->group(function (): void {
        Route::post('/login', 'login')->name('login');

        Route::middleware(['auth:sanctum', 'admin'])
            ->group(function (): void {
                Route::post('/logout', 'logout')->name('logout');
                Route::get('/me', 'me')->name('me');
            });
    });