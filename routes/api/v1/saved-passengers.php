<?php

use App\Modules\Api\SavedPassengers\Http\Controllers\SavedPassengerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('saved-passengers')
    ->as('saved-passengers.')
    ->controller(SavedPassengerController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/{savedPassenger}', 'show')->name('show');
        Route::put('/{savedPassenger}', 'update')->name('update');
        Route::delete('/{savedPassenger}', 'destroy')->middleware('throttle:20,1')->name('destroy');
    });
