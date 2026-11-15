<?php

use App\Modules\Admin\Orders\Http\Controllers\AgentBookingController;
use Illuminate\Support\Facades\Route;

Route::controller(AgentBookingController::class)->group(function (): void {
    Route::get('/orders/book', 'create')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book');

    Route::post('/orders/book', 'store')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book.store');

    Route::get('/orders/book/customers', 'customers')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book.customers');

    Route::get('/orders/book/hotel-lookup/status', 'hotelStatus')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book.hotel-lookup.status');

    Route::get('/orders/book/hotel-lookup/cities', 'hotelCities')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book.hotel-lookup.cities');

    Route::post('/orders/book/hotel-lookup/hotels', 'hotelSearch')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book.hotel-lookup.hotels');

    Route::get('/orders/book/hotel-lookup/offers', 'hotelOffers')
        ->middleware('permission:orders.agent-book')
        ->name('orders.book.hotel-lookup.offers');
});
