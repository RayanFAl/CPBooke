<?php

use App\Modules\Api\SavedAddresses\Http\Controllers\SavedAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('saved-addresses')
    ->as('saved-addresses.')
    ->controller(SavedAddressController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
        Route::get('/{savedAddress}', 'show')->name('show');
        Route::match(['put', 'patch'], '/{savedAddress}', 'update')->name('update');
        Route::post('/{savedAddress}/set-default', 'setDefault')->name('set-default');
        Route::delete('/{savedAddress}', 'destroy')->middleware('throttle:30,1')->name('destroy');
    });
