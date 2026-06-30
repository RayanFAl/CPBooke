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
    });