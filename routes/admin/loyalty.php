<?php

use App\Modules\Admin\Loyalty\Http\Controllers\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::get('/loyalty', [LoyaltyController::class, 'index'])
    ->middleware('permission:loyalty.view')
    ->name('loyalty.index');

Route::get('/promo', [LoyaltyController::class, 'promo'])
    ->middleware('permission:loyalty.view')
    ->name('promo.index');

Route::get('/loyalty/settings', [LoyaltyController::class, 'showSettings'])
    ->middleware('permission:loyalty.settings.manage')
    ->name('loyalty.settings.show');

Route::match(['put', 'patch'], '/loyalty/settings', [LoyaltyController::class, 'updateSettings'])
    ->middleware('permission:loyalty.settings.manage')
    ->name('loyalty.settings.update');

Route::post('/loyalty/tiers', [LoyaltyController::class, 'storeTier'])
    ->middleware('permission:loyalty.manage')
    ->name('loyalty.tiers.store');

Route::post('/loyalty/tiers/{loyaltyTier}/duplicate', [LoyaltyController::class, 'duplicateTier'])
    ->middleware('permission:loyalty.manage')
    ->name('loyalty.tiers.duplicate');

Route::put('/loyalty/tiers/{loyaltyTier}', [LoyaltyController::class, 'updateTier'])
    ->middleware('permission:loyalty.manage')
    ->name('loyalty.tiers.update');

Route::put('/loyalty/rules/{loyaltyRule}', [LoyaltyController::class, 'updateRule'])
    ->middleware('permission:loyalty.manage-rules')
    ->name('loyalty.rules.update');

Route::put('/loyalty/benefits/{loyaltyBenefit}', [LoyaltyController::class, 'updateBenefit'])
    ->middleware('permission:loyalty.manage-benefits')
    ->name('loyalty.benefits.update');

Route::get('/loyalty/company-rates', [LoyaltyController::class, 'showCompanyRates'])
    ->middleware('permission:loyalty.view')
    ->name('loyalty.company-rates.show');

Route::put('/loyalty/company-rates', [LoyaltyController::class, 'syncCompanyRates'])
    ->middleware('permission:loyalty.manage-benefits')
    ->name('loyalty.company-rates.sync');
