<?php

use App\Modules\Admin\Content\Http\Controllers\ContentPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:settings.manage')->prefix('content')->as('content.')->group(function (): void {
    Route::get('/', [ContentPageController::class, 'index'])->name('index');
    Route::get('/create', [ContentPageController::class, 'create'])->name('create');
    Route::post('/', [ContentPageController::class, 'store'])->name('store');
    Route::get('/{page}/edit', [ContentPageController::class, 'edit'])->name('edit');
    Route::put('/{page}', [ContentPageController::class, 'update'])->name('update');
    Route::delete('/{page}', [ContentPageController::class, 'destroy'])->name('destroy');
});
