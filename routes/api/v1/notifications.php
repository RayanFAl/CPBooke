<?php

use App\Modules\Api\Notifications\Http\Controllers\NotificationApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('notifications')
    ->as('notifications.')
    ->controller(NotificationApiController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/unread', 'unread')->name('unread');
        Route::post('/mark-as-read', 'markAsRead')->name('mark-as-read');
    });