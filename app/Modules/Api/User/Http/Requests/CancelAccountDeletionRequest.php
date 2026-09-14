<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class CancelAccountDeletionRequest extends ApiFormRequest
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
            'login' => ['required_without:id_token', 'nullable', 'string', 'max:191'],
            'password' => ['required_with:login', 'nullable', 'string'],
            'id_token' => ['required_without:login', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required_without' => 'Provide login credentials or a Google ID token.',
            'id_token.required_without' => 'Provide login credentials or a Google ID token.',
            'password.required_with' => 'The password is required.',
        ];
    }
}
