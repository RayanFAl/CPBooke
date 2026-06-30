<?php

use App\Modules\Api\Admin\Loyalty\Http\Controllers\LoyaltySettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin/loyalty')
    ->as('admin.loyalty.')
    ->controller(LoyaltySettingsController::class)
    ->group(function (): void {
        Route::get('/settings', 'show')
            ->middleware('permission:loyalty.settings.manage')
            ->name('settings.show');

        Route::match(['put', 'patch'], '/settings', 'update')
            ->middleware('permission:loyalty.settings.manage')
            ->name('settings.update');
    });