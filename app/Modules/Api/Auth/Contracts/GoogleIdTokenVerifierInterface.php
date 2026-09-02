<?php

namespace App\Modules\Api\Auth\Contracts;

interface GoogleIdTokenVerifierInterface
{
    /**
     * Verify a Google ID token and return its claims.
     *
     * @return array{
     *     sub: string,
     *     email: string,
     *     name?: string,
     *     picture?: string,
     *     email_verified?: bool|string
     * }
     */
    public function verify(string $idToken): array;
}
