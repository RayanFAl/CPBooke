<?php

return [
    'environments' => [
        'sandbox',
        'production',
    ],

    'auth_types' => [
        'bearer_token',
        'api_key',
        'api_key_secret',
        'oauth2',
        'custom',
    ],

    'services' => [
        'flight' => 'Flights',
        'hotel' => 'Hotels',
        'insurance' => 'Insurance',
        'esim' => 'eSIM',
        'visa' => 'Visa',
        'activities' => 'Activities',
        'transfers' => 'Transfers',
    ],

    'default_timeout' => (int) env('PROVIDER_API_DEFAULT_TIMEOUT', 30),

    'connection_test_path' => env('PROVIDER_API_CONNECTION_TEST_PATH', '/'),

    'api_key_header' => env('PROVIDER_API_KEY_HEADER', 'X-API-Key'),

    'endpoint_catalog' => [
        'flight.search' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/search', 'label' => 'Search Flights'],
        'flight.results' => ['service' => 'flight', 'method' => 'GET', 'path' => '/flights/results/{search_uuid}', 'label' => 'Fetch Results'],
        'flight.price' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/price', 'label' => 'Price Flight'],
        'flight.select' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/select', 'label' => 'Select Flight'],
        'flight.book' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/book', 'label' => 'Book Flight'],
        'flight.seatmap' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/seatmap', 'label' => 'Seat Map'],
        'flight.issue' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/{booking_id}/tickets/issue', 'label' => 'Issue Ticket'],
        'flight.cancel' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/{booking_id}/cancel', 'label' => 'Cancel Flight Booking'],
        'flight.refund' => ['service' => 'flight', 'method' => 'POST', 'path' => '/flights/{booking_id}/refund', 'label' => 'Refund Flight Booking'],
        'hotel.autocomplete' => ['service' => 'hotel', 'method' => 'GET', 'path' => '/hotels/autocomplete', 'label' => 'Hotel Autocomplete'],
        'hotel.search' => ['service' => 'hotel', 'method' => 'POST', 'path' => '/hotels/search', 'label' => 'Search Hotels'],
        'hotel.details' => ['service' => 'hotel', 'method' => 'GET', 'path' => '/hotels/details', 'label' => 'Hotel Details'],
        'hotel.select' => ['service' => 'hotel', 'method' => 'POST', 'path' => '/hotels/select', 'label' => 'Select Hotel Offer'],
        'hotel.book' => ['service' => 'hotel', 'method' => 'POST', 'path' => '/hotels/book', 'label' => 'Book Hotel'],
        'insurance.travel.references' => ['service' => 'insurance', 'method' => 'GET', 'path' => '/insurance/travel/references', 'label' => 'Travel References'],
        'insurance.travel.price' => ['service' => 'insurance', 'method' => 'POST', 'path' => '/insurance/travel/price', 'label' => 'Travel Quote'],
        'insurance.travel.issue' => ['service' => 'insurance', 'method' => 'POST', 'path' => '/insurance/travel/issue', 'label' => 'Travel Issue'],
        'esim.countries' => ['service' => 'esim', 'method' => 'GET', 'path' => '/esim/countries', 'label' => 'eSIM Countries'],
        'esim.search' => ['service' => 'esim', 'method' => 'POST', 'path' => '/esim/search', 'label' => 'Search eSIM'],
        'esim.packages' => ['service' => 'esim', 'method' => 'GET', 'path' => '/esim/results/{search_uuid}/packages', 'label' => 'Search eSIM Packages'],
        'esim.select' => ['service' => 'esim', 'method' => 'POST', 'path' => '/esim/select', 'label' => 'Select eSIM Package'],
        'esim.book' => ['service' => 'esim', 'method' => 'POST', 'path' => '/esim/book', 'label' => 'Book eSIM'],
        'esim.refund' => ['service' => 'esim', 'method' => 'POST', 'path' => '/orders/{order_id}/esim-items/{item_id}/refund', 'label' => 'Refund eSIM Item'],
        'provider.connection_test' => ['service' => 'system', 'method' => 'GET', 'path' => '/', 'label' => 'Provider Connection Test'],
        'sync.flight' => ['service' => 'flight', 'method' => 'POST', 'path' => '/api/v1/orders/sync-flight', 'label' => 'Sync Flight Order'],
        'sync.hotel' => ['service' => 'hotel', 'method' => 'POST', 'path' => '/api/v1/orders/sync-hotel', 'label' => 'Sync Hotel Order'],
        'sync.insurance' => ['service' => 'insurance', 'method' => 'POST', 'path' => '/api/v1/orders/sync-insurance', 'label' => 'Sync Insurance Order'],
        'sync.esim' => ['service' => 'esim', 'method' => 'POST', 'path' => '/api/v1/orders/sync-esim', 'label' => 'Sync eSIM Order'],
        'sync.bundle' => ['service' => 'flight', 'method' => 'POST', 'path' => '/api/v1/orders/sync-bundle', 'label' => 'Sync Bundle Order'],
    ],

    'masked_secret' => '••••••••••••',
];
