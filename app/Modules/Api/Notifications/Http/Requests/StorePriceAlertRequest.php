<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class StorePriceAlertRequest extends ApiFormRequest
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
            'target_price' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
