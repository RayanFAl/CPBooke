<?php

use App\Modules\Partners\Http\Controllers\PartnerMeController;
use App\Modules\Partners\Http\Controllers\PartnerOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('partner.key')
    ->prefix('partner')
    ->as('partner.')
    ->group(function (): void {
        Route::get('/me', PartnerMeController::class)->name('me');
        Route::get('/orders/{order}', [PartnerOrderController::class, 'show'])->name('orders.show');
    });
