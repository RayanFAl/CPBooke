<?php

namespace App\Support\Http;

/**
 * SSL verify option for outbound Http:: clients on Windows/local PHP.
 */
final class HttpSsl
{
    public static function verifyOption(?string $bundlePath = null): bool|string
    {
        $bundle = $bundlePath ?: storage_path('certs/cacert.pem');

        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            return $bundle;
        }

        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        return true;
    }
}
