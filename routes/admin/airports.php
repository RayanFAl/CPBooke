<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AirportController;

Route::resource('airports', AirportController::class);
