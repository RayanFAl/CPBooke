<?php

use App\Modules\Admin\Loyalty\Http\Controllers\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::get('/loyalty', [LoyaltyController::class, 'index'])
    ->middleware('permission:loyalty.view')
    ->name('loyalty.index');

Route::get('/loyalty/settings', [LoyaltyController::class, 'showSettings'])
    ->middleware('permission:loyalty.settings.manage')
    ->name('loyalty.settings.show');

Route::match(['put', 'patch'], '/loyalty/settings', [LoyaltyController::class, 'updateSettings'])
    ->middleware('permission:loyalty.settings.manage')
    ->name('loyalty.settings.update');

Route::put('/loyalty/tiers/{loyaltyTier}', [LoyaltyController::class, 'updateTier'])
    ->middleware('permission:loyalty.manage')
    ->name('loyalty.tiers.update');

Route::put('/loyalty/rules/{loyaltyRule}', [LoyaltyController::class, 'updateRule'])
    ->middleware('permission:loyalty.manage-rules')
    ->name('loyalty.rules.update');

Route::put('/loyalty/benefits/{loyaltyBenefit}', [LoyaltyController::class, 'updateBenefit'])
    ->middleware('permission:loyalty.manage-benefits')
    ->name('loyalty.benefits.update');