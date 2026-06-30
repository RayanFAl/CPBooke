<?php

use App\Modules\Admin\Notifications\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationsController::class, 'index'])
    ->middleware('permission:notifications.view')
    ->name('notifications.index');

Route::post('/notifications/logs/{notificationLog}/retry', [NotificationsController::class, 'retry'])
    ->middleware('permission:notifications.retry-failed')
    ->name('notifications.retry');

Route::put('/notifications/templates/{notificationTemplate}', [NotificationsController::class, 'updateTemplate'])
    ->middleware('permission:notifications.manage-templates')
    ->name('notifications.templates.update');