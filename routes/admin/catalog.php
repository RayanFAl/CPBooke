<?php

use App\Modules\Admin\Catalog\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:settings.manage')->prefix('catalog')->as('catalog.')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index'])->name('index');
    Route::get('/create', [CatalogController::class, 'create'])->name('create');
    Route::post('/', [CatalogController::class, 'store'])->name('store');
    Route::get('/{catalog}/edit', [CatalogController::class, 'edit'])->name('edit');
    Route::post('/{catalog}', [CatalogController::class, 'update'])->name('update');
    Route::delete('/{catalog}', [CatalogController::class, 'destroy'])->name('destroy');
});
