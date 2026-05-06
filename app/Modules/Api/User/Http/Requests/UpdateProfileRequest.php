<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\DTO\UpdateProfileDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
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
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Convert the request payload to a DTO.
     */
    public function toDto(): UpdateProfileDTO
    {
        return UpdateProfileDTO::fromArray($this->validated());
    }
}