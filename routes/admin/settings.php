<?php

use App\Modules\Admin\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SettingsController::class, 'index'])
    ->middleware('permission:settings.manage')
    ->name('settings.index');

Route::match(['put', 'patch'], '/settings', [SettingsController::class, 'update'])
    ->middleware('permission:settings.manage')
    ->name('settings.update');
