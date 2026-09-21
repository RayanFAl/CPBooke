<?php

use App\Modules\Api\Loyalty\Http\Controllers\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('loyalty')
    ->as('loyalty.')
    ->controller(LoyaltyController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::get('/rates', 'rates')->name('rates');
        Route::get('/rates/resolve', 'resolve')->name('rates.resolve');
    });
