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
});