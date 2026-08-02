<?php

use App\Modules\Admin\Partners\Http\Controllers\PartnerController;
use Illuminate\Support\Facades\Route;

Route::get('/partners', [PartnerController::class, 'index'])
    ->middleware('permission:partners.view')
    ->name('partners.index');

Route::get('/partners/create', [PartnerController::class, 'create'])
    ->middleware('permission:partners.manage')
    ->name('partners.create');

Route::post('/partners', [PartnerController::class, 'store'])
    ->middleware('permission:partners.manage')
    ->name('partners.store');

Route::get('/partners/{partner}', [PartnerController::class, 'show'])
    ->middleware('permission:partners.view')
    ->name('partners.show');

Route::get('/partners/{partner}/edit', [PartnerController::class, 'edit'])
    ->middleware('permission:partners.manage')
    ->name('partners.edit');

Route::match(['put', 'patch'], '/partners/{partner}', [PartnerController::class, 'update'])
    ->middleware('permission:partners.manage')
    ->name('partners.update');

Route::post('/partners/{partner}/api-keys', [PartnerController::class, 'storeApiKey'])
    ->middleware('permission:partners.manage')
    ->name('partners.api-keys.store');

Route::post('/partners/{partner}/api-keys/{apiKey}/revoke', [PartnerController::class, 'revokeApiKey'])
    ->middleware('permission:partners.manage')
    ->name('partners.api-keys.revoke');

Route::post('/partners/{partner}/webhooks', [PartnerController::class, 'storeWebhook'])
    ->middleware('permission:partners.manage')
    ->name('partners.webhooks.store');

Route::match(['put', 'patch'], '/partners/{partner}/webhooks/{webhook}', [PartnerController::class, 'updateWebhook'])
    ->middleware('permission:partners.manage')
    ->name('partners.webhooks.update');

Route::delete('/partners/{partner}/webhooks/{webhook}', [PartnerController::class, 'destroyWebhook'])
    ->middleware('permission:partners.manage')
    ->name('partners.webhooks.destroy');
