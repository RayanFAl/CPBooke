<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default currency for customer wallets
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('CUSTOMER_WALLET_DEFAULT_CURRENCY', 'LYD'),

    /*
    |--------------------------------------------------------------------------
    | Supported wallet currencies (strict allow-list)
    |--------------------------------------------------------------------------
    |
    | Every customer gets one wallet per currency in this list.
    |
    */
    'supported_currencies' => ['LYD', 'USD', 'EUR'],

    /*
    |--------------------------------------------------------------------------
    | Test wallet top-up (mobile app mock funding)
    |--------------------------------------------------------------------------
    |
    | When disabled, POST /api/v1/wallet/test/top-up returns 403.
    | Never enable in production without additional safeguards.
    |
    */
    'test_mode' => (bool) env('WALLET_TEST_MODE', false),

    'test_top_up_max' => (float) env('WALLET_TEST_TOP_UP_MAX', 1000),

    'test_top_up_min' => (float) env('WALLET_TEST_TOP_UP_MIN', 1),
];
