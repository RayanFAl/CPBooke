<?php

use App\Modules\Api\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('orders')
    ->as('orders.')
    ->controller(OrderController::class)
    ->group(function (): void {
        Route::post('/sync-flight', 'syncFlight')
            ->middleware('throttle:60,1')
            ->name('sync-flight');
        Route::post('/sync-esim', 'syncEsim')
            ->middleware('throttle:60,1')
            ->name('sync-esim');
        Route::post('/sync-insurance', 'syncInsurance')
            ->middleware('throttle:60,1')
            ->name('sync-insurance');
        Route::post('/sync-hotel', 'syncHotel')
            ->middleware('throttle:60,1')
            ->name('sync-hotel');
        Route::post('/sync-bundle', 'syncBundle')
            ->middleware('throttle:60,1')
            ->name('sync-bundle');
        Route::post('/', 'store')
            ->middleware('throttle:30,1')
            ->name('store');
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->name('show');
    });
