<?php

namespace App\Modules\Api\DTO;

final readonly class UpdateSavedVehicleDTO
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
        $dto = CreateSavedVehicleDTO::fromArray($data);

        return new self(
            type: $dto->type,
            label: $dto->label,
            isDefault: $dto->isDefault,
            beneficiaryName: $dto->beneficiaryName,
            beneficiaryPhone: $dto->beneficiaryPhone,
            email: $dto->email,
            vehicleTypeId: $dto->vehicleTypeId,
            vehicleColorId: $dto->vehicleColorId,
            vehicleLicensingAuthorityId: $dto->vehicleLicensingAuthorityId,
            vehicleManufactureYear: $dto->vehicleManufactureYear,
            vehicleChassisNumber: $dto->vehicleChassisNumber,
            vehiclePlateNumber: $dto->vehiclePlateNumber,
            payload: $dto->payload,
            documentTypeId: $dto->documentTypeId,
            vehicleNationality: $dto->vehicleNationality,
            address: $dto->address,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
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
