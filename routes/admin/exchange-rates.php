<?php

use App\Modules\Admin\ExchangeRates\Http\Controllers\ExchangeRatesController;
use Illuminate\Support\Facades\Route;

Route::get('/exchange-rates', [ExchangeRatesController::class, 'index'])
    ->middleware('permission:exchange-rates.view,exchange-rates.manage')
    ->name('exchange-rates.index');

Route::get('/exchange-rates/daily-report', [ExchangeRatesController::class, 'dailyReport'])
    ->middleware('permission:exchange-rates.view,exchange-rates.manage')
    ->name('exchange-rates.daily-report');

Route::get('/exchange-rates/daily-report/print', [ExchangeRatesController::class, 'dailyReportPrint'])
    ->middleware('permission:exchange-rates.view,exchange-rates.manage')
    ->name('exchange-rates.daily-report.print');

Route::match(['put', 'patch'], '/exchange-rates', [ExchangeRatesController::class, 'update'])
    ->middleware('permission:exchange-rates.manage')
    ->name('exchange-rates.update');
