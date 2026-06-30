<?php

namespace App\Modules\Api\SavedPassengers\Http\Requests;

use App\Models\SavedPassenger;
use App\Modules\Api\DTO\UpdateSavedPassengerDTO;
use App\Modules\Api\SavedPassengers\Rules\UniquePassportNumberForUser;

class UpdateSavedPassengerRequest extends SavedPassengerFormRequest
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
        /** @var SavedPassenger|null $savedPassenger */
        $savedPassenger = $this->route('savedPassenger');

        return [
            ...$this->passengerRules(),
            'type' => ['required', 'string', 'in:ADT,CHD,INF'],
            'document_type' => ['required', 'string', 'in:passport,national_id'],
            'passport_number' => [
                'required',
                'string',
                'max:50',
                new UniquePassportNumberForUser($this->user(), $savedPassenger?->id),
            ],
        ];
    }

    /**
     * Convert the request payload to a DTO.
     */
    public function toDto(): UpdateSavedPassengerDTO
    {
        return UpdateSavedPassengerDTO::fromArray($this->validated());
    }
}
