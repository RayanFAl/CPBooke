<?php

use App\Http\Controllers\Admin\BooknowAirportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:settings.manage')->group(function (): void {
    Route::resource('booknow_airports', BooknowAirportController::class);
});
