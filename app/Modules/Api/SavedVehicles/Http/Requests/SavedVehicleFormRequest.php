<?php

namespace App\Modules\Api\SavedVehicles\Http\Requests;

use App\Models\SavedVehicle;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

abstract class SavedVehicleFormRequest extends ApiFormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray(), 'Validation failed'),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function vehicleRules(): array
    {
        $type = $this->input('type');
        $currentYear = (int) now()->format('Y') + 1;

        return [
            'type' => ['required', 'string', Rule::in(SavedVehicle::types())],
            'label' => ['nullable', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
            'beneficiary_name' => ['required', 'string', 'max:180'],
            'beneficiary_phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicle_manufacture_year' => ['required', 'integer', 'min:1950', 'max:'.$currentYear],
            'vehicle_chassis_number' => ['required', 'string', 'max:80'],
            'vehicle_plate_number' => ['required', 'string', 'max:40'],
            'payload' => ['nullable', 'numeric', 'gte:0'],
            'document_type_id' => ['nullable', 'integer', 'min:1'],
            'vehicle_type_id' => [
                Rule::requiredIf($type === SavedVehicle::TYPE_COMPULSORY),
                'nullable',
                'integer',
                'min:1',
            ],
            'vehicle_color_id' => [
                Rule::requiredIf($type === SavedVehicle::TYPE_COMPULSORY),
                'nullable',
                'integer',
                'min:1',
            ],
            'vehicle_licensing_authority_id' => [
                Rule::requiredIf($type === SavedVehicle::TYPE_COMPULSORY),
                'nullable',
                'integer',
                'min:1',
            ],
            'vehicle_nationality' => ['nullable', 'string', 'size:3'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
