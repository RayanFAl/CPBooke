<?php

namespace App\Modules\Api\DTO;

final readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
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
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'],
            password: $data['password'],
            deviceName: $data['device_name'] ?? 'mobile-app',
        );
    }
}