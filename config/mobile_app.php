<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile app download page
    |--------------------------------------------------------------------------
    |
    | Place APK files in storage/app/releases/ using a versioned filename such as
    | booke-1.2.0+120.apk. The latest release is picked automatically. Optional
    | release.json can add release notes or force-update rules.
    |
    */

    'name' => env('MOBILE_APP_NAME', env('APP_NAME', 'BookNow')),

    'version' => env('MOBILE_APK_VERSION', '1.0.0'),

    'version_code' => (int) env('MOBILE_APK_VERSION_CODE', 1),

    'releases_directory' => storage_path('app/releases'),

    'cache_seconds' => (int) env('MOBILE_APK_CACHE_SECONDS', 60),

    'max_upload_kb' => (int) env('MOBILE_APK_MAX_UPLOAD_KB', 512000),

    'apk_path' => env('MOBILE_APK_PATH') ?: storage_path('app/releases/booke.apk'),

    'apk_filename' => env('MOBILE_APK_FILENAME', 'booke.apk'),

    'play_store_url' => env('MOBILE_PLAY_STORE_URL'),

    'app_store_url' => env('MOBILE_APP_STORE_URL'),

];
