<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passenger API token lifetimes (seconds)
    |--------------------------------------------------------------------------
    |
    | remember_me=false → short session
    | remember_me=true  → longer access + refresh window
    |
    */

    'access_token_ttl_seconds' => (int) env('API_ACCESS_TOKEN_TTL_SECONDS', 3600),

    'access_token_remember_ttl_seconds' => (int) env('API_ACCESS_TOKEN_REMEMBER_TTL_SECONDS', 60 * 60 * 24 * 7),

    'refresh_token_ttl_seconds' => (int) env('API_REFRESH_TOKEN_TTL_SECONDS', 60 * 60 * 24 * 7),

    'refresh_token_remember_ttl_seconds' => (int) env('API_REFRESH_TOKEN_REMEMBER_TTL_SECONDS', 60 * 60 * 24 * 30),

];
