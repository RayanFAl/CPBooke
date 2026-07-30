<?php

use App\Modules\Api\User\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('users')
    ->as('users.')
    ->controller(ProfileController::class)
    ->group(function (): void {
        Route::get('/profile', 'show')->name('profile.show');
        Route::put('/profile', 'update')->name('profile.update');

        Route::post('/profile/avatar', 'uploadAvatar')
            ->middleware('throttle:10,1')
            ->name('profile.avatar.upload');
        Route::delete('/profile/avatar', 'deleteAvatar')
            ->middleware('throttle:10,1')
            ->name('profile.avatar.destroy');

        Route::post('/email/change-request', 'requestEmailChange')
            ->middleware('throttle:5,1')
            ->name('email.change-request');
        Route::post('/email/verify', 'verifyEmailChange')
            ->middleware('throttle:10,1')
            ->name('email.verify-change');

        Route::post('/verify/email/send', 'sendEmailVerification')
            ->middleware('throttle:5,1')
            ->name('verify.email.send');
        Route::post('/verify/email/confirm', 'confirmEmailVerification')
            ->middleware('throttle:10,1')
            ->name('verify.email.confirm');
        Route::post('/verify/phone/send', 'sendPhoneVerification')
            ->middleware('throttle:5,1')
            ->name('verify.phone.send');
        Route::post('/verify/phone/confirm', 'confirmPhoneVerification')
            ->middleware('throttle:10,1')
            ->name('verify.phone.confirm');
    });
