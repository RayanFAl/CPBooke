<?php

namespace App\Modules\Api\DTO;

use App\Models\SavedVehicle;

final readonly class CreateSavedVehicleDTO
{
    public function __construct(
        public string $type,
        public ?string $label,
        public bool $isDefault,
        public string $beneficiaryName,
        public string $beneficiaryPhone,
        public ?string $email,
        public ?int $vehicleTypeId,
        public ?int $vehicleColorId,
        public ?int $vehicleLicensingAuthorityId,
        public int $vehicleManufactureYear,
        public string $vehicleChassisNumber,
        public string $vehiclePlateNumber,
        public ?float $payload,
        public ?int $documentTypeId,
        public ?string $vehicleNationality,
        public ?string $address,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) $data['type'],
            label: isset($data['label']) ? trim((string) $data['label']) ?: null : null,
            isDefault: (bool) ($data['is_default'] ?? false),
            beneficiaryName: trim((string) $data['beneficiary_name']),
            beneficiaryPhone: trim((string) $data['beneficiary_phone']),
            email: isset($data['email']) && trim((string) $data['email']) !== ''
                ? strtolower(trim((string) $data['email']))
                : null,
            vehicleTypeId: isset($data['vehicle_type_id']) ? (int) $data['vehicle_type_id'] : null,
            vehicleColorId: isset($data['vehicle_color_id']) ? (int) $data['vehicle_color_id'] : null,
            vehicleLicensingAuthorityId: isset($data['vehicle_licensing_authority_id'])
                ? (int) $data['vehicle_licensing_authority_id']
                : null,
            vehicleManufactureYear: (int) $data['vehicle_manufacture_year'],
            vehicleChassisNumber: SavedVehicle::normalizeChassis((string) $data['vehicle_chassis_number']),
            vehiclePlateNumber: SavedVehicle::normalizePlate((string) $data['vehicle_plate_number']),
            payload: isset($data['payload']) && $data['payload'] !== null && $data['payload'] !== ''
                ? round((float) $data['payload'], 2)
                : null,
            documentTypeId: isset($data['document_type_id']) ? (int) $data['document_type_id'] : null,
            vehicleNationality: isset($data['vehicle_nationality']) && trim((string) $data['vehicle_nationality']) !== ''
                ? strtoupper(trim((string) $data['vehicle_nationality']))
                : null,
            address: isset($data['address']) ? trim((string) $data['address']) ?: null : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(int $userId): array
    {
        return [
            'user_id' => $userId,
            'type' => $this->type,
            'label' => $this->label,
            'is_default' => $this->isDefault,
            'beneficiary_name' => $this->beneficiaryName,
            'beneficiary_phone' => $this->beneficiaryPhone,
            'email' => $this->email,
            'vehicle_type_id' => $this->vehicleTypeId,
            'vehicle_color_id' => $this->vehicleColorId,
            'vehicle_licensing_authority_id' => $this->vehicleLicensingAuthorityId,
            'vehicle_manufacture_year' => $this->vehicleManufactureYear,
            'vehicle_chassis_number' => $this->vehicleChassisNumber,
            'vehicle_plate_number' => $this->vehiclePlateNumber,
            'payload' => $this->payload !== null
                ? number_format($this->payload, 2, '.', '')
                : null,
            'document_type_id' => $this->documentTypeId,
            'vehicle_nationality' => $this->vehicleNationality,
            'address' => $this->address,
        ];
    }
}
