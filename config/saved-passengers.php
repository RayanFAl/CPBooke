<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport scan image storage
    |--------------------------------------------------------------------------
    |
    | Images are stored on a private disk (default: local → storage/app/private).
    | Set PASSPORT_IMAGE_DISK=s3 for private S3 (bucket SSE recommended).
    | Passenger list/show APIs never return a public URL — only metadata flags.
    |
    */

    'passport_image_disk' => env('PASSPORT_IMAGE_DISK', 'local'),

    'passport_image_directory' => 'passport-images',

    /** Max upload size in kilobytes (default 6MB). */
    'passport_image_max_kilobytes' => (int) env('PASSPORT_IMAGE_MAX_KB', 6144),

    /**
     * Soft retention: images older than this many days are purged by the
     * scheduled command (and DB metadata cleared). Null/0 disables auto-purge.
     */
    'passport_image_retention_days' => (int) env('PASSPORT_IMAGE_RETENTION_DAYS', 365),

];
