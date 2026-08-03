<?php

use App\Modules\Admin\Airports\Http\Controllers\AirportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:settings.manage')->group(function (): void {
    Route::post('airports/import', [AirportController::class, 'import'])->name('airports.import');
    Route::get('airports/featured/search', [AirportController::class, 'searchFeaturedCandidates'])->name('airports.featured.search');
    Route::put('airports/featured', [AirportController::class, 'updateFeatured'])->name('airports.featured.update');
    Route::post('airports/{airport}/featured/toggle', [AirportController::class, 'toggleFeatured'])->name('airports.featured.toggle');
    Route::resource('airports', AirportController::class)->except(['show']);
});
