<?php

namespace App\Modules\Api\DTO;

final readonly class GoogleAuthDTO
{
    public function __construct(
        public string $idToken,
        public string $deviceName,
        public bool $rememberMe = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idToken: $data['id_token'],
            deviceName: $data['device_name'] ?? 'mobile-app',
            rememberMe: (bool) ($data['remember_me'] ?? false),
        );
    }
}
