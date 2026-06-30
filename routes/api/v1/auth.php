<?php

use App\Modules\Api\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->as('auth.')
    ->controller(AuthController::class)
    ->group(function (): void {
        Route::post('/register', 'register')->name('register');
        Route::post('/login', 'login')->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', 'logout')->name('logout');
            Route::get('/me', 'me')->name('me');
        });
    });