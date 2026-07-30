<?php

use App\Modules\Admin\Approvals\Http\Controllers\ApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/approvals', [ApprovalController::class, 'index'])
    ->middleware('permission:approvals.view')
    ->name('approvals.index');

Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])
    ->middleware('permission:approvals.view')
    ->name('approvals.show');

Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])
    ->middleware('permission:approvals.approve')
    ->name('approvals.approve');

Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])
    ->middleware('permission:approvals.approve')
    ->name('approvals.reject');

Route::post('/approvals/{approval}/retry', [ApprovalController::class, 'retry'])
    ->middleware('permission:approvals.approve')
    ->name('approvals.retry');
