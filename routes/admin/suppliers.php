<?php

use App\Modules\Admin\Suppliers\Http\Controllers\ProviderApiController;
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

Route::post('/suppliers/{supplier}/api-config', [ProviderApiController::class, 'upsertConfig'])
    ->middleware('permission:suppliers.api-config.manage')
    ->name('suppliers.api-config.upsert');

Route::post('/suppliers/{supplier}/api-config/{environment}/disable', [ProviderApiController::class, 'disableConfig'])
    ->middleware('permission:suppliers.api-config.manage')
    ->name('suppliers.api-config.disable');

Route::post('/suppliers/{supplier}/api-config/{environment}/test', [ProviderApiController::class, 'testConnection'])
    ->middleware('permission:suppliers.api-config.manage')
    ->name('suppliers.api-config.test');

Route::post('/suppliers/{supplier}/api-config/{environment}/audit-credentials', [ProviderApiController::class, 'auditCredentialView'])
    ->middleware('permission:suppliers.credentials.view')
    ->name('suppliers.api-config.audit-credentials');

Route::post('/suppliers/{supplier}/services', [ProviderApiController::class, 'syncServices'])
    ->middleware('permission:suppliers.api-config.manage')
    ->name('suppliers.services.sync');
