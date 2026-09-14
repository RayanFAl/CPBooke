<?php

use App\Modules\Api\Currency\Http\Controllers\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::prefix('currency')
    ->as('currency.')
    ->middleware('throttle:60,1')
    ->controller(CurrencyController::class)
    ->group(function (): void {
        Route::get('/rates', 'rates')->name('rates');
        Route::post('/convert', 'convert')->name('convert');
    });
