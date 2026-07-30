<?php

use App\Modules\Admin\Suppliers\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/suppliers', [SupplierController::class, 'index'])
    ->middleware('permission:suppliers.view')
    ->name('suppliers.index');

Route::get('/suppliers/create', [SupplierController::class, 'create'])
    ->middleware('permission:suppliers.manage')
    ->name('suppliers.create');

Route::post('/suppliers', [SupplierController::class, 'store'])
    ->middleware('permission:suppliers.manage')
    ->name('suppliers.store');

Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
    ->middleware('permission:suppliers.view')
    ->name('suppliers.show');

Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
    ->middleware('permission:suppliers.manage')
    ->name('suppliers.edit');

Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])
    ->middleware('permission:suppliers.manage')
    ->name('suppliers.update');
