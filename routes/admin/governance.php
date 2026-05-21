<?php

use App\Modules\Admin\Governance\Http\Controllers\GovernanceController;
use Illuminate\Support\Facades\Route;

Route::get('/governance/dashboard', GovernanceController::class)
    ->middleware('permission:governance.view')
    ->name('governance.dashboard');