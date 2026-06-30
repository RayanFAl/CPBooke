<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BooknowAirportController;

Route::resource('booknow_airports', BooknowAirportController::class);
