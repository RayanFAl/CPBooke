<?php

use App\Modules\Admin\ProviderWallets\Http\Controllers\ProviderWalletController;
use Illuminate\Support\Facades\Route;

Route::get('/provider-wallets', [ProviderWalletController::class, 'index'])
    ->middleware('permission:provider-wallets.view')
    ->name('provider-wallets.index');

Route::get('/provider-wallets/create', [ProviderWalletController::class, 'create'])
    ->middleware('permission:provider-wallets.manage')
    ->name('provider-wallets.create');

Route::post('/provider-wallets', [ProviderWalletController::class, 'store'])
    ->middleware('permission:provider-wallets.manage')
    ->name('provider-wallets.store');

Route::get('/provider-wallets/{providerWallet}', [ProviderWalletController::class, 'show'])
    ->middleware('permission:provider-wallets.view')
    ->name('provider-wallets.show');

Route::get('/provider-wallets/{providerWallet}/print', [ProviderWalletController::class, 'printStatement'])
    ->middleware('permission:provider-wallets.view')
    ->name('provider-wallets.print');

Route::post('/provider-wallets/{providerWallet}/deposit', [ProviderWalletController::class, 'deposit'])
    ->middleware('permission:provider-wallets.manage')
    ->name('provider-wallets.deposit');

Route::post('/provider-wallets/{providerWallet}/adjust', [ProviderWalletController::class, 'adjust'])
    ->middleware('permission:provider-wallets.manage')
    ->name('provider-wallets.adjust');
