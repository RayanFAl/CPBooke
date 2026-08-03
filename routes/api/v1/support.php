<?php

use App\Modules\Api\Support\Http\Controllers\SupportApiController;
use App\Modules\Api\Support\Http\Controllers\SupportChatApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('support/tickets')
    ->as('support.tickets.')
    ->controller(SupportApiController::class)
    ->group(function (): void {
        Route::get('/current', 'currentConversation')->name('current');
        Route::post('/messages', 'sendMessage')
            ->middleware('throttle:30,1')
            ->name('messages');
        Route::post('/', 'store')
            ->middleware('throttle:20,1')
            ->name('store');
        Route::get('/', 'index')->name('index');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::post('/{id}/reply', 'reply')
            ->middleware('throttle:30,1')
            ->whereNumber('id')
            ->name('reply');
    });

Route::middleware('auth:sanctum')
    ->prefix('support/chat/tickets')
    ->as('support.chat.tickets.')
    ->controller(SupportChatApiController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{ticket}', 'show')->whereNumber('ticket')->name('show');
        Route::get('/{ticket}/messages', 'messages')->whereNumber('ticket')->name('messages.index');
        Route::post('/{ticket}/messages', 'storeMessage')
            ->middleware('throttle:30,1')
            ->whereNumber('ticket')
            ->name('messages.store');
        Route::get('/{ticket}/typing', 'showTyping')->whereNumber('ticket')->name('typing.show');
        Route::post('/{ticket}/typing', 'typing')
            ->middleware('throttle:60,1')
            ->whereNumber('ticket')
            ->name('typing');
        Route::post('/{ticket}/seen', 'seen')->whereNumber('ticket')->name('seen');
    });
