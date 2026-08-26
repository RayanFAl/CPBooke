<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Travel Assistant
    |--------------------------------------------------------------------------
    |
    | Gemini powers natural-language understanding for Booke Voice/Chat.
    | The AI only extracts structured intents/slots and ranks real API offers.
    | It never searches flights, invents prices, or books.
    |
    */

    'provider' => env('AI_PROVIDER', 'gemini'),

    'enabled' => env('AI_ENABLED', true),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta'
        ),
        'timeout' => (int) env('GEMINI_TIMEOUT', 12),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1024),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.2),
        'max_offers_for_recommendation' => (int) env('GEMINI_MAX_OFFERS', 8),
        'max_conversation_turns' => (int) env('GEMINI_MAX_CONVERSATION_TURNS', 6),
        // Path to CA bundle for Windows/local PHP without curl.cainfo (see storage/certs/cacert.pem)
        'ca_bundle' => env('GEMINI_CA_BUNDLE', storage_path('certs/cacert.pem')),
        // true | false | path — false only allowed in local env
        'ssl_verify' => env('GEMINI_SSL_VERIFY'),
    ],

    'timezone' => env('AI_TIMEZONE', 'Africa/Tripoli'),

    'default_currency' => env('AI_DEFAULT_CURRENCY', 'LYD'),

    'log_requests' => env('AI_LOG_REQUESTS', true),

    'log_retention_days' => (int) env('AI_LOG_RETENTION_DAYS', 90),

];
