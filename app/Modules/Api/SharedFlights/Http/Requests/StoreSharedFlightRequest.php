<?php

namespace App\Modules\Api\SharedFlights\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSharedFlightRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray(), 'Validation failed'),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['required', 'array'],
            'selected_offer' => ['required', 'array'],
        ];
    }
}
