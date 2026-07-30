<?php

namespace App\Modules\Api\DTO;

final readonly class UpdateSavedAddressDTO
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
        $create = CreateSavedAddressDTO::fromArray($data);

        return new self(
            title: $create->title,
            address: $create->address,
            latitude: $create->latitude,
            longitude: $create->longitude,
            isDefault: $create->isDefault,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->isDefault,
        ];
    }
}
