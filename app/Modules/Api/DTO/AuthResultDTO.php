<?php

namespace App\Modules\Api\DTO;

use App\Models\User;

final readonly class AuthResultDTO
{
    public function __construct(
        public User $user,
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $expiresAt,
        public bool $rememberMe = false,
    ) {
    }

    /**
     * Backward-compatible alias used by older clients/tests.
     */
    public function token(): string
    {
        return $this->accessToken;
    }
}
