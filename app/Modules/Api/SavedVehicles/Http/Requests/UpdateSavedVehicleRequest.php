<?php

namespace App\Modules\Api\SavedVehicles\Http\Requests;

use App\Models\SavedVehicle;
use App\Modules\Api\DTO\UpdateSavedVehicleDTO;
use App\Modules\Api\SavedVehicles\Rules\UniqueChassisNumberForUser;

class UpdateSavedVehicleRequest extends SavedVehicleFormRequest
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
        /** @var SavedVehicle|null $savedVehicle */
        $savedVehicle = $this->route('savedVehicle');

        return [
            ...$this->vehicleRules(),
            'vehicle_chassis_number' => [
                'required',
                'string',
                'max:80',
                new UniqueChassisNumberForUser($this->user(), $savedVehicle?->id),
            ],
        ];
    }

    public function toDto(): UpdateSavedVehicleDTO
    {
        return UpdateSavedVehicleDTO::fromArray($this->validated());
    }
}
