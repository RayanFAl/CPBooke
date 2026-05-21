<?php

use App\Modules\Admin\Support\Http\Controllers\ResolutionReportController;
use App\Modules\Admin\Support\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

Route::get('/support', [SupportController::class, 'index'])
	->middleware('permission:support.view')
	->name('support.index');

Route::get('/support/create', [SupportController::class, 'create'])
	->middleware('permission:support.view')
	->name('support.create');

Route::post('/support', [SupportController::class, 'store'])
	->middleware('permission:support.view')
	->name('support.store');

Route::get('/support/{supportTicket}', [SupportController::class, 'show'])
	->middleware('permission:support.view')
	->name('support.show');

Route::post('/support/{supportTicket}/reply', [SupportController::class, 'reply'])
	->middleware('permission:support.view')
	->name('support.reply');

Route::post('/support/{supportTicket}/order/cancel', [SupportController::class, 'cancelOrder'])
	->middleware('permission:support.view')
	->name('support.order.cancel');

Route::post('/support/{supportTicket}/order/full-refund', [SupportController::class, 'fullRefund'])
	->middleware('permission:support.view')
	->name('support.order.full-refund');

Route::post('/support/{supportTicket}/order/partial-refund', [SupportController::class, 'partialRefund'])
	->middleware('permission:support.view')
	->name('support.order.partial-refund');

Route::post('/support/{supportTicket}/order/reverse-refund', [SupportController::class, 'reverseRefund'])
	->middleware('permission:support.view')
	->name('support.order.reverse-refund');

Route::post('/support/{supportTicket}/order/compensation', [SupportController::class, 'compensation'])
	->middleware('permission:support.view')
	->name('support.order.compensation');

Route::put('/support/{supportTicket}/status', [SupportController::class, 'updateStatus'])
	->middleware('permission:support.view')
	->name('support.update-status');

Route::put('/support/{supportTicket}/assignment', [SupportController::class, 'assign'])
	->middleware('permission:support.view')
	->name('support.assign');

Route::match(['post', 'put'], '/support/{supportTicket}/resolution-report', [ResolutionReportController::class, 'upsert'])
	->middleware('permission:support.view')
	->name('support.resolution-report.upsert');