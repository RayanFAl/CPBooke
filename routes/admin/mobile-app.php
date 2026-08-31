<?php

use App\Modules\Admin\MobileApp\Http\Controllers\MobileAppController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:settings.manage')->prefix('mobile-app')->as('mobile-app.')->group(function (): void {
    Route::get('/', [MobileAppController::class, 'index'])->name('index');
    Route::post('/apk', [MobileAppController::class, 'uploadApk'])->name('apk.upload');
});
