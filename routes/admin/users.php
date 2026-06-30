<?php

use App\Modules\Admin\Users\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::controller(UsersController::class)->group(function (): void {
	Route::get('/users', 'index')->middleware('permission:users.view')->name('users.index');
	Route::get('/users/create', 'create')->middleware('permission:users.create')->name('users.create');
	Route::post('/users', 'store')->middleware('permission:users.create')->name('users.store');
	Route::get('/users/{user}/edit', 'edit')->middleware('permission:users.update')->name('users.edit');
	Route::put('/users/{user}', 'update')->middleware('permission:users.update')->name('users.update');
	Route::post('/users/{user}/toggle-status', 'toggleStatus')->middleware('permission:users.update')->name('users.toggle-status');
	Route::get('/users/{user}', 'show')->middleware('permission:users.view')->name('users.show');
});