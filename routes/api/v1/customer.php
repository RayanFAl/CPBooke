<?php

use App\Modules\Api\User\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/customer/account/deletion/cancel', [ProfileController::class, 'cancelDeletion'])
    ->middleware('throttle:5,1')
    ->name('customer.account.deletion.cancel');

Route::middleware('auth:sanctum')
    ->prefix('customer')
    ->as('customer.')
    ->controller(ProfileController::class)
    ->group(function (): void {
        Route::delete('/account', 'destroy')
            ->middleware('throttle:5,1')
            ->name('account.destroy');
    });
