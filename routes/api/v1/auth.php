<?php

use App\Modules\Api\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->as('auth.')
    ->controller(AuthController::class)
    ->group(function (): void {
        Route::post('/register', 'register')
            ->middleware('throttle:10,1')
            ->name('register');
        Route::post('/login', 'login')
            ->middleware('throttle:10,1')
            ->name('login');
        Route::post('/google', 'google')
            ->middleware('throttle:10,1')
            ->name('google');
        Route::post('/refresh', 'refresh')
            ->middleware('throttle:30,1')
            ->name('refresh');

        Route::post('/forgot-password', 'forgotPassword')
            ->middleware('throttle:5,1')
            ->name('forgot-password');
        Route::post('/verify-reset-otp', 'verifyResetOtp')
            ->middleware('throttle:10,1')
            ->name('verify-reset-otp');
        Route::post('/reset-password', 'resetPassword')
            ->middleware('throttle:5,1')
            ->name('reset-password');

        Route::post('/2fa/verify', 'twoFactorVerify')
            ->middleware('throttle:10,1')
            ->name('2fa.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', 'logout')->name('logout');
            Route::post('/logout-all', 'logoutAll')->name('logout-all');
            Route::get('/me', 'me')->name('me');
            Route::get('/sessions', 'sessions')->name('sessions');
            Route::delete('/sessions/{session}', 'destroySession')->name('sessions.destroy');
            Route::put('/change-password', 'changePassword')->name('change-password');

            Route::get('/2fa/status', 'twoFactorStatus')->name('2fa.status');
            Route::post('/2fa/enable', 'twoFactorEnable')->name('2fa.enable');
            Route::post('/2fa/confirm', 'twoFactorConfirm')->name('2fa.confirm');
            Route::post('/2fa/disable', 'twoFactorDisable')->name('2fa.disable');
        });
    });
