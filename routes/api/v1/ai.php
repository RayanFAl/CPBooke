<?php

use App\Modules\Api\AI\Http\Controllers\TravelAssistantController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai')
    ->as('ai.')
    ->middleware('throttle:40,1')
    ->group(function (): void {
        Route::post('/travel-assistant', TravelAssistantController::class)
            ->name('travel-assistant');
    });
