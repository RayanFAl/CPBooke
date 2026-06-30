<?php

namespace App\Modules\Api\SavedPassengers\Http\Requests;

use App\Modules\Api\DTO\CreateSavedPassengerDTO;
use App\Modules\Api\SavedPassengers\Rules\UniquePassportNumberForUser;

class StoreSavedPassengerRequest extends SavedPassengerFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->passengerRules(),
            'type' => ['required', 'string', 'in:ADT,CHD,INF'],
            'document_type' => ['required', 'string', 'in:passport,national_id'],
            'passport_number' => [
                'required',
                'string',
                'max:50',
                new UniquePassportNumberForUser($this->user()),
            ],
        ];
    }

    /**
     * Convert the request payload to a DTO.
     */
    public function toDto(): CreateSavedPassengerDTO
    {
        return CreateSavedPassengerDTO::fromArray($this->validated());
    }
}
