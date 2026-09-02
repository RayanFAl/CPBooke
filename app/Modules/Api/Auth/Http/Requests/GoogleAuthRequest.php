<?php

namespace App\Modules\Api\Auth\Http\Requests;

use App\Modules\Api\DTO\GoogleAuthDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class GoogleAuthRequest extends ApiFormRequest
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
            'provider' => ['required', 'string', Rule::in(['google'])],
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): GoogleAuthDTO
    {
        return GoogleAuthDTO::fromArray($this->validated());
    }
}
