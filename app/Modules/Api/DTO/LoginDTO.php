<?php

namespace App\Modules\Api\DTO;

final readonly class LoginDTO
{
    public function __construct(
        public string $login,
        public string $password,
        public string $deviceName,
    ) {
    }

    /**
     * Create a DTO from validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            login: $data['login'],
            password: $data['password'],
            deviceName: $data['device_name'] ?? 'mobile-app',
        );
    }
}