<?php

use App\Modules\Api\Favorites\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('favorites')
    ->as('favorites.')
    ->controller(FavoriteController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
        Route::get('/check', 'check')->name('check');
        Route::delete('/', 'destroyByKey')->middleware('throttle:30,1')->name('destroy-by-key');
        Route::delete('/{favorite}', 'destroy')->middleware('throttle:30,1')->name('destroy');
    });
