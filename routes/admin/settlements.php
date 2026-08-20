<?php

use App\Modules\Admin\Settlements\Http\Controllers\SettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/settlements', [SettlementController::class, 'index'])
    ->middleware('permission:settlements.view')
    ->name('settlements.index');

Route::get('/settlements/create', [SettlementController::class, 'create'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.create');

Route::post('/settlements', [SettlementController::class, 'store'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.store');

Route::get('/settlements/{settlement}', [SettlementController::class, 'show'])
    ->middleware('permission:settlements.view')
    ->name('settlements.show');

Route::post('/settlements/{settlement}/import-invoice', [SettlementController::class, 'importInvoice'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.import-invoice');

Route::post('/settlements/{settlement}/attachments', [SettlementController::class, 'storeAttachment'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.attachments.store');

Route::get('/settlements/{settlement}/attachments/{attachment}', [SettlementController::class, 'downloadAttachment'])
    ->middleware('permission:settlements.view')
    ->name('settlements.attachments.download');

Route::post('/settlements/{settlement}/compare', [SettlementController::class, 'compare'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.compare');

Route::post('/settlements/{settlement}/items/{item}/resolve', [SettlementController::class, 'resolveItem'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.items.resolve');

Route::post('/settlements/{settlement}/approve', [SettlementController::class, 'approve'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.approve');

Route::post('/settlements/{settlement}/close', [SettlementController::class, 'close'])
    ->middleware('permission:settlements.manage')
    ->name('settlements.close');

Route::post('/settlements/{settlement}/reopen', [SettlementController::class, 'reopen'])
    ->middleware('permission:settlements.reopen')
    ->name('settlements.reopen');
