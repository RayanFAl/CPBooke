<?php

namespace App\Modules\Api\DTO;

final readonly class CreateSavedAddressDTO
{
    public function __construct(
        public string $title,
        public string $address,
        public float $latitude,
        public float $longitude,
        public bool $isDefault = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: trim((string) $data['title']),
            address: trim((string) $data['address']),
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            isDefault: (bool) ($data['is_default'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(int $userId): array
    {
        return [
            'user_id' => $userId,
            'title' => $this->title,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->isDefault,
        ];
    }
}
