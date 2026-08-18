<?php

use App\Modules\Api\Notifications\Http\Controllers\NotificationApiController;
use App\Modules\Api\Notifications\Http\Controllers\TravelMarketingApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('notifications')
    ->as('notifications.')
    ->group(function (): void {
        Route::controller(NotificationApiController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/unread-count', 'unreadCount')->name('unread-count');
            Route::get('/unread', 'unread')->name('unread');

            Route::get('/preferences', 'preferences')->name('preferences.show');
            Route::put('/preferences', 'updatePreferences')->name('preferences.update');

            Route::post('/read-all', 'markAllAsRead')->name('read-all');
            Route::post('/mark-as-read', 'markAsRead')->name('mark-as-read');
            Route::post('/clear', 'clear')->name('clear');
            Route::delete('/', 'clear')->name('destroy-all');

            Route::post('/devices', 'registerDevice')->name('devices.register');
            Route::patch('/devices', 'updateDevice')->name('devices.update');
            Route::delete('/devices', 'destroyDevice')->name('devices.destroy');
            Route::post('/push-test', 'pushTest')
                ->middleware('throttle:10,1')
                ->name('push-test');
        });

        Route::controller(TravelMarketingApiController::class)->group(function (): void {
            Route::post('/search-intents', 'storeSearchIntent')->name('search-intents.store');
            Route::get('/price-alerts', 'priceAlerts')->name('price-alerts.index');
            Route::post('/price-alerts', 'storePriceAlert')->name('price-alerts.store');
            Route::delete('/price-alerts/{priceAlert}', 'destroyPriceAlert')->name('price-alerts.destroy');
        });

        Route::controller(NotificationApiController::class)->group(function (): void {
            Route::post('/{notification}/read', 'markOneAsRead')->name('read');
            Route::patch('/{notification}/read', 'markOneAsRead')->name('read.patch');
            Route::delete('/{notification}', 'destroy')->name('destroy');
        });
    });
