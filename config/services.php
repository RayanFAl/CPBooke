<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google Sign-In (mobile app → POST /api/v1/auth/google)
    |
    | GOOGLE_CLIENT_ID must be the **Web** OAuth client ID (used as ID token `aud`).
    | Do NOT set the Android client ID here — `azp` in the token stays Android and is
    | not validated. The endpoint verifies the **id_token** JWT, not an access token.
    |
    | Local Windows/PHP: ensure storage/certs/cacert.pem exists so the server can fetch
    | https://www.googleapis.com/oauth2/v3/certs (no internet/firewall → 401).
    */
    'google' => [
        'client_id' => env(
            'GOOGLE_CLIENT_ID',
            '769318459-qk6rtuti9liddd0csfppjh99h24i50r9.apps.googleusercontent.com',
        ),
        'ca_bundle' => env('GOOGLE_CA_BUNDLE', storage_path('certs/cacert.pem')),
    ],

    'notifications' => [
        'fcm_server_key' => env('FCM_SERVER_KEY'),
        'fcm_sender_id' => env('FCM_SENDER_ID'),
        // Preferred: Firebase Admin service-account JSON (FCM HTTP v1)
        'firebase_credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase_credentials.json'),
        'sms_endpoint' => env('SMS_ENDPOINT'),
        'sms_token' => env('SMS_TOKEN'),
        'whatsapp_endpoint' => env('WHATSAPP_ENDPOINT'),
        'whatsapp_token' => env('WHATSAPP_TOKEN'),
    ],

];
