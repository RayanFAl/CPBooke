<?php

namespace App\Modules\Api\DTO;

final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $country,
        public bool $phoneProvided = false,
        public bool $countryProvided = false,
    ) {
    }

    /**
     * Create a DTO from validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $phoneProvided = array_key_exists('phone', $data);
        $countryProvided = array_key_exists('country', $data);

        return new self(
            name: (string) $data['name'],
            phone: $phoneProvided ? (filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null) : null,
            country: $countryProvided ? (filled($data['country'] ?? null) ? trim((string) $data['country']) : null) : null,
            phoneProvided: $phoneProvided,
            countryProvided: $countryProvided,
        );
    }
}
