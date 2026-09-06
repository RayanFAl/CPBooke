<?php

use App\Modules\Admin\Notifications\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationsController::class, 'index'])
    ->middleware('permission:notifications.view')
    ->name('notifications.index');

Route::post('/notifications/push-test', [NotificationsController::class, 'sendTestPush'])
    ->middleware('permission:notifications.view')
    ->name('notifications.push-test');

Route::post('/notifications/firebase-credentials', [NotificationsController::class, 'uploadFirebaseCredentials'])
    ->middleware('permission:notifications.manage-templates')
    ->name('notifications.firebase-credentials');

Route::post('/notifications/firebase-disconnect', [NotificationsController::class, 'disconnectFirebase'])
    ->middleware('permission:notifications.manage-templates')
    ->name('notifications.firebase-disconnect');

Route::post('/notifications/firebase-restore', [NotificationsController::class, 'restoreFirebase'])
    ->middleware('permission:notifications.manage-templates')
    ->name('notifications.firebase-restore');

Route::post('/notifications/firebase-test', [NotificationsController::class, 'testFirebase'])
    ->middleware('permission:notifications.view')
    ->name('notifications.firebase-test');

Route::post('/notifications/template-test', [NotificationsController::class, 'sendTestTemplate'])
    ->middleware('permission:notifications.view')
    ->name('notifications.template-test');

Route::post('/notifications/logs/{notificationLog}/retry', [NotificationsController::class, 'retry'])
    ->middleware('permission:notifications.retry-failed')
    ->name('notifications.retry');

Route::post('/notifications/templates/sync', [NotificationsController::class, 'syncTemplates'])
    ->middleware('permission:notifications.manage-templates')
    ->name('notifications.templates.sync');

Route::put('/notifications/templates/{notificationTemplate}', [NotificationsController::class, 'updateTemplate'])
    ->middleware('permission:notifications.manage-templates')
    ->name('notifications.templates.update');
