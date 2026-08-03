<?php

use App\Modules\Admin\Home\Http\Controllers\HomeContentController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:settings.manage')->prefix('home')->as('home.')->group(function (): void {
    Route::get('/', [HomeContentController::class, 'index'])->name('index');

    Route::get('/banners/create', [HomeContentController::class, 'createBanner'])->name('banners.create');
    Route::post('/banners', [HomeContentController::class, 'storeBanner'])->name('banners.store');
    Route::get('/banners/{banner}/edit', [HomeContentController::class, 'editBanner'])->name('banners.edit');
    Route::post('/banners/{banner}', [HomeContentController::class, 'updateBanner'])->name('banners.update');
    Route::delete('/banners/{banner}', [HomeContentController::class, 'destroyBanner'])->name('banners.destroy');

    Route::get('/offers/create', [HomeContentController::class, 'createOffer'])->name('offers.create');
    Route::post('/offers', [HomeContentController::class, 'storeOffer'])->name('offers.store');
    Route::get('/offers/{offer}/edit', [HomeContentController::class, 'editOffer'])->name('offers.edit');
    Route::post('/offers/{offer}', [HomeContentController::class, 'updateOffer'])->name('offers.update');
    Route::delete('/offers/{offer}', [HomeContentController::class, 'destroyOffer'])->name('offers.destroy');
});
