<?php

use App\Modules\Admin\ProviderHealth\Http\Controllers\ProviderHealthController;
use Illuminate\Support\Facades\Route;

Route::get('/provider-health', [ProviderHealthController::class, 'index'])
    ->middleware('permission:provider-health.view')
    ->name('provider-health.index');
