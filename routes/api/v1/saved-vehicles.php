<?php

use App\Modules\Api\SavedVehicles\Http\Controllers\SavedVehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('saved-vehicles')
    ->as('saved-vehicles.')
    ->controller(SavedVehicleController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/{savedVehicle}', 'show')->name('show');
        Route::match(['put', 'patch'], '/{savedVehicle}', 'update')->name('update');
        Route::delete('/{savedVehicle}', 'destroy')->middleware('throttle:20,1')->name('destroy');
    });
