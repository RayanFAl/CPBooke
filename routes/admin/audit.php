<?php

use App\Modules\Admin\Audit\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

Route::get('/audit', [AuditController::class, 'index'])
    ->middleware('permission:audit.view')
    ->name('audit.index');
