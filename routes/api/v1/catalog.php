<?php

use App\Modules\Api\Catalog\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')
    ->as('catalog.')
    ->middleware('throttle:60,1')
    ->controller(CatalogController::class)
    ->group(function (): void {
        Route::get('/', 'content')->name('content');
        Route::get('/options', 'options')->name('options');
        Route::get('/market', 'market')->name('market');
    });
