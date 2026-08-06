<?php

use App\Modules\Api\Content\Http\Controllers\ContentPageController;
use Illuminate\Support\Facades\Route;

Route::prefix('pages')
    ->as('pages.')
    ->middleware('throttle:60,1')
    ->controller(ContentPageController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{slug}', 'show')->name('show')->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    });
