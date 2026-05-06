<?php

use App\Modules\Admin\Support\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

Route::get('/support', SupportController::class)
	->middleware('permission:support.view')
	->name('support.index');