<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class UpsertTravelSearchIntentRequest extends ApiFormRequest
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
            'return_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:departure_date'],
            'lowest_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
