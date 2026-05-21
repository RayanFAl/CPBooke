<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Customer web chat uses the same support API from the first-party web
    | session, while Flutter and other remote consumers continue using tokens.
    | These domains enable same-origin and local stateful API requests only.
    |
    */

    'stateful' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
        .(($appUrlHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST)) ? ','.$appUrlHost : '')
        .(($appUrlPort = parse_url((string) env('APP_URL', ''), PHP_URL_PORT)) && $appUrlHost ? ','.$appUrlHost.':'.$appUrlPort : '')
    ))))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | The web guard enables first-party browser sessions to consume the chat
    | API, while token authentication remains available for external clients.
    |
    */

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];