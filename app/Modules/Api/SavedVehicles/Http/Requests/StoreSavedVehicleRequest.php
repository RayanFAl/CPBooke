<?php

namespace App\Modules\Api\SavedVehicles\Http\Requests;

use App\Modules\Api\DTO\CreateSavedVehicleDTO;
use App\Modules\Api\SavedVehicles\Rules\UniqueChassisNumberForUser;

class StoreSavedVehicleRequest extends SavedVehicleFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->vehicleRules(),
            'vehicle_chassis_number' => [
                'required',
                'string',
                'max:80',
                new UniqueChassisNumberForUser($this->user()),
            ],
        ];
    }

    public function toDto(): CreateSavedVehicleDTO
    {
        return CreateSavedVehicleDTO::fromArray($this->validated());
    }
}
