<?php

use App\Modules\Admin\Orders\Http\Controllers\OrdersController;
use Illuminate\Support\Facades\Route;

Route::controller(OrdersController::class)->group(function (): void {
	Route::get('/orders', 'index')
		->middleware('permission:orders.view')
		->name('orders.index');

	Route::get('/orders/{order}', 'show')
		->middleware('permission:orders.view')
		->name('orders.show');

	Route::put('/orders/{order}/status', 'updateStatus')
		->middleware('permission:orders.change-status')
		->name('orders.update-status');

	Route::put('/orders/{order}/payment-status', 'updatePaymentStatus')
		->middleware('permission:orders.change-status')
		->name('orders.update-payment-status');

	Route::put('/orders/{order}/notes', 'updateNotes')
		->middleware('permission:orders.update-notes')
		->name('orders.update-notes');

	Route::post('/orders/{order}/cancel', 'cancel')
		->middleware('permission:support.cancel-order')
		->name('orders.cancel');

	Route::post('/orders/{order}/full-refund', 'fullRefund')
		->middleware('permission:support.full-refund')
		->name('orders.full-refund');

	Route::post('/orders/{order}/partial-refund', 'partialRefund')
		->middleware('permission:support.partial-refund')
		->name('orders.partial-refund');

	Route::post('/orders/{order}/reverse-refund', 'reverseRefund')
		->middleware('permission:finance.reverse-refund')
		->name('orders.reverse-refund');

	Route::post('/orders/{order}/compensation', 'compensation')
		->middleware('permission:support.partial-refund')
		->name('orders.compensation');
});