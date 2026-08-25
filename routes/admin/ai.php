<?php

use App\Modules\Admin\AI\Http\Controllers\AiSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai/settings')
    ->as('ai.settings.')
    ->middleware('permission:settings.manage')
    ->controller(AiSettingsController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::match(['put', 'patch'], '/', 'update')->name('update');
        Route::post('/test', 'testConnection')->name('test');
    });

Route::get('/ai/logs', [\App\Modules\Admin\AI\Http\Controllers\AiRequestLogController::class, 'index'])
    ->middleware('permission:settings.manage')
    ->name('ai.logs.index');
