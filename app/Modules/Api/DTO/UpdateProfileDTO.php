<?php

namespace App\Modules\Api\DTO;

final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $country,
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
            phone: $data['phone'] ?: null,
            country: $data['country'] ?: null,
        );
    }
}