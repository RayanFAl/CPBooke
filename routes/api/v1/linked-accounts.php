<?php

use App\Modules\Api\LinkedAccounts\Http\Controllers\LinkedAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('linked-accounts')
    ->as('linked-accounts.')
    ->controller(LinkedAccountController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');

        Route::post('/requests', 'storeRequest')->middleware('throttle:20,1')->name('requests.store');
        Route::get('/requests', 'indexRequests')->name('requests.index');
        Route::post('/requests/{linkedAccountRequest}/respond', 'respond')
            ->middleware('throttle:30,1')
            ->name('requests.respond');

        Route::post('/search', 'search')->middleware('throttle:30,1')->name('search');

        Route::patch('/{linkedAccount}/permissions', 'updatePermissions')
            ->middleware('throttle:30,1')
            ->name('permissions');
        Route::delete('/{linkedAccount}', 'destroy')
            ->middleware('throttle:20,1')
            ->name('destroy');
    });
