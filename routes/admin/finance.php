<?php

use App\Modules\Admin\Finance\Http\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;

Route::get('/finance', FinanceController::class)
	->middleware('permission:finance.view')
	->name('finance.index');