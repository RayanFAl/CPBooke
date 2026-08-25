<?php

use App\Modules\Api\HotelReviews\Http\Controllers\HotelReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->controller(HotelReviewController::class)
    ->group(function (): void {
        Route::post('/hotel-reviews', 'store')
            ->middleware('throttle:30,1')
            ->name('hotel-reviews.store');

        Route::prefix('hotels')->as('hotels.')->group(function (): void {
            Route::get('/bookings/{bookingId}/review', 'showForBooking')
                ->name('bookings.review.show');

            Route::post('/bookings/{bookingId}/reviews', 'store')
                ->middleware('throttle:30,1')
                ->name('bookings.reviews.store');
        });
    });

Route::prefix('hotels')
    ->as('hotels.')
    ->middleware('throttle:60,1')
    ->controller(HotelReviewController::class)
    ->group(function (): void {
        Route::get('/{hotelId}/reviews', 'indexForHotel')
            ->where('hotelId', '[A-Za-z0-9._-]+')
            ->name('reviews.index');
    });
