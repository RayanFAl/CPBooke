<?php

namespace App\Modules\Api\Auth\Http\Requests;

use App\Modules\Api\DTO\LoginDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Convert the request payload to a DTO.
     */
    public function toDto(): LoginDTO
    {
        return LoginDTO::fromArray($this->validated());
    }
}