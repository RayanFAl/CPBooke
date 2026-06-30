<?php

use App\Modules\Admin\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', SettingsController::class)
	->middleware('permission:settings.manage')
	->name('settings.index');