<?php

use App\Modules\Admin\Monitoring\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/monitoring', [MonitoringController::class, 'index'])
    ->middleware('permission:monitoring.view')
    ->name('monitoring.index');

Route::post('/monitoring/run-probes', [MonitoringController::class, 'runProbes'])
    ->middleware('permission:monitoring.manage')
    ->name('monitoring.run-probes');
