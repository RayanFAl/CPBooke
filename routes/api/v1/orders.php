<?php

use App\Modules\Api\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('orders')
    ->as('orders.')
    ->controller(OrderController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->name('show');
    });