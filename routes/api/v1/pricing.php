<?php

use App\Modules\Api\Pricing\Http\Controllers\PricingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('pricing')
    ->as('pricing.')
    ->controller(PricingController::class)
    ->group(function (): void {
        Route::match(['get', 'post'], '/preview', 'preview')->name('preview');
    });