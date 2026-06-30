<?php

namespace App\Modules\Api\SavedPassengers\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class SavedPassengerFormRequest extends ApiFormRequest
{
    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray(), 'Validation failed'),
        );
    }

    /**
     * Shared validation rules for saved passenger payloads.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function passengerRules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:ADT,CHD,INF'],
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', 'in:M,F'],
            'nationality' => ['required', 'string', 'size:3'],
            'country_of_residence' => ['nullable', 'string', 'size:3'],
            'document_type' => ['sometimes', 'string', 'in:passport,national_id'],
            'passport_number' => ['required', 'string', 'max:50'],
            'passport_issue_country' => ['nullable', 'string', 'size:3'],
            'passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passport_expiry' => ['required', 'date', 'after:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'seat_preference' => ['nullable', 'string', 'max:30'],
            'meal_preference' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
