<?php

use App\Modules\Admin\Finance\Http\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;

Route::get('/finance', [FinanceController::class, 'index'])
	->middleware('permission:finance.view')
	->name('finance.index');

Route::get('/finance/export.csv', [FinanceController::class, 'exportCsv'])
	->middleware('permission:finance.export')
	->name('finance.export.csv');

Route::post('/finance/reconcile', [FinanceController::class, 'reconcile'])
	->middleware('permission:finance.reconcile')
	->name('finance.reconcile');