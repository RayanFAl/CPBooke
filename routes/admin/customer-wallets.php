<?php

use App\Modules\Admin\CustomerWallets\Http\Controllers\CustomerWalletController;
use Illuminate\Support\Facades\Route;

Route::get('/customer-wallets', [CustomerWalletController::class, 'index'])
    ->middleware('permission:customer-wallets.view')
    ->name('customer-wallets.index');

Route::get('/customer-wallets/{customerWallet}', [CustomerWalletController::class, 'show'])
    ->middleware('permission:customer-wallets.view')
    ->name('customer-wallets.show');

Route::post('/customer-wallets/{customerWallet}/credit', [CustomerWalletController::class, 'credit'])
    ->middleware('permission:customer-wallets.manage')
    ->name('customer-wallets.credit');

Route::post('/customer-wallets/{customerWallet}/debit', [CustomerWalletController::class, 'debit'])
    ->middleware('permission:customer-wallets.manage')
    ->name('customer-wallets.debit');

Route::post('/customer-wallets/{customerWallet}/freeze', [CustomerWalletController::class, 'freeze'])
    ->middleware('permission:customer-wallets.manage')
    ->name('customer-wallets.freeze');

Route::post('/customer-wallets/{customerWallet}/unfreeze', [CustomerWalletController::class, 'unfreeze'])
    ->middleware('permission:customer-wallets.manage')
    ->name('customer-wallets.unfreeze');

Route::post('/users/{user}/customer-wallet', [CustomerWalletController::class, 'createForUser'])
    ->middleware('permission:customer-wallets.manage')
    ->name('users.customer-wallet.create');
