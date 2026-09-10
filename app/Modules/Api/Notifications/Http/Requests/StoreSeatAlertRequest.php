<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class StoreSeatAlertRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'max:64'],
            'destination' => ['required', 'string', 'max:64'],
            'departure_date' => ['sometimes', 'nullable', 'date'],
            'flight_number' => ['sometimes', 'nullable', 'string', 'max:32'],
            'offer_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'cabin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'min_seats' => ['required', 'integer', 'min:1', 'max:9'],
        ];
    }
}
