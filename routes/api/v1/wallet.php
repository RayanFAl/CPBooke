<?php

use App\Modules\Api\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('wallet')
    ->as('wallet.')
    ->middleware('auth:sanctum')
    ->controller(WalletController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/transactions', 'transactions')->name('transactions');
        Route::post('/test/top-up', 'testTopUp')
            ->middleware('throttle:20,1')
            ->name('test.top-up');
        Route::post('/pay-order', 'payOrder')
            ->middleware('throttle:30,1')
            ->name('pay-order');
        Route::get('/{currency}', 'show')
            ->where('currency', 'LYD|USD|EUR|lyd|usd|eur')
            ->name('show');
    });
