<?php

namespace App\Modules\Api\Favorites\Http\Requests;

use App\Models\Favorite;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DestroyFavoriteByKeyRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Favorite::types())],
            'item_key' => ['required', 'string', 'max:191'],
        ];
    }
}
