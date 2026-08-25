<?php

use App\Modules\Admin\Orders\Http\Controllers\OrdersController;
use Illuminate\Support\Facades\Route;

Route::controller(OrdersController::class)->group(function (): void {
	Route::get('/orders', 'index')
		->middleware('permission:orders.view')
		->name('orders.index');

	Route::get('/orders/create', 'create')
		->middleware('permission:orders.create')
		->name('orders.create');

	Route::post('/orders', 'store')
		->middleware('permission:orders.create')
		->name('orders.store');

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
});