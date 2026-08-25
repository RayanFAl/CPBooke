<?php

use App\Modules\Admin\Search\Http\Controllers\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [GlobalSearchController::class, 'index'])
    ->middleware('permission:search.view')
    ->name('search.index');

Route::get('/search/suggest', [GlobalSearchController::class, 'suggest'])
    ->middleware('permission:search.view')
    ->name('search.suggest');
