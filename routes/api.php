<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
	->as('api.v1.')
	->group(function (): void {
		require __DIR__.'/api/v1/admin-auth.php';
		require __DIR__.'/api/v1/airports.php';
		require __DIR__.'/api/v1/admin-loyalty.php';
		require __DIR__.'/api/v1/auth.php';
		require __DIR__.'/api/v1/favorites.php';
		require __DIR__.'/api/v1/home.php';
		require __DIR__.'/api/v1/content.php';
		require __DIR__.'/api/v1/notifications.php';
		require __DIR__.'/api/v1/orders.php';
		require __DIR__.'/api/v1/pricing.php';
		require __DIR__.'/api/v1/saved-passengers.php';
		require __DIR__.'/api/v1/saved-vehicles.php';
		require __DIR__.'/api/v1/saved-addresses.php';
		require __DIR__.'/api/v1/support.php';
		require __DIR__.'/api/v1/user.php';
		require __DIR__.'/api/v1/wallet.php';
	});
