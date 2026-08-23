<?php

use App\Modules\Api\App\Http\Controllers\AppUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')
    ->as('app.')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('/update', AppUpdateController::class)->name('update');
    });
