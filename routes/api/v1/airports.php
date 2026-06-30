<?php

use App\Modules\Api\Airports\Http\Controllers\AirportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('airports')
    ->as('airports.')
    ->controller(AirportController::class)
    ->group(function (): void {
        Route::get('/featured', 'featured')->name('featured');
        Route::get('/', 'index')->name('index');
    });
