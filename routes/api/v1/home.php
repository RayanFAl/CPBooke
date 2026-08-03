<?php

use App\Modules\Api\Home\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('home')
    ->as('home.')
    ->middleware('throttle:60,1')
    ->controller(HomeController::class)
    ->group(function (): void {
        Route::get('/content', 'content')->name('content');
        Route::get('/banners', 'banners')->name('banners');
        Route::get('/offers', 'offers')->name('offers');
    });
