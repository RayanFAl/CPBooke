<?php

use App\Modules\Api\SharedFlights\Http\Controllers\SharedFlightController;
use Illuminate\Support\Facades\Route;

Route::prefix('shared-flight')
    ->as('shared-flight.')
    ->controller(SharedFlightController::class)
    ->group(function (): void {
        Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
        Route::get('/{token}', 'show')->middleware('throttle:60,1')->name('show');
    });
