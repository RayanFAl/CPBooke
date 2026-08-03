<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default insufficient-balance policy for new wallets
    |--------------------------------------------------------------------------
    |
    | true  = allow the balance to go negative (credit / overdraft)
    | false = reject the debit when funds are insufficient
    |
    */
    'default_allow_negative' => (bool) env('WALLET_ALLOW_NEGATIVE', false),

    'default_environment' => env('WALLET_DEFAULT_ENVIRONMENT', 'production'),

    'environments' => [
        'production',
        'sandbox',
    ],
];
