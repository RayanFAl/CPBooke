<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class DeleteAccountRequest extends ApiFormRequest
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
        $user = $this->user();

        if ($user !== null && filled($user->google_id)) {
            return [
                'confirmation' => ['required', 'string', Rule::in(['DELETE'])],
            ];
        }

        return [
            'password' => ['required', 'current_password:sanctum'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Type DELETE to confirm account deletion.',
            'password.current_password' => 'The password is incorrect.',
        ];
    }
}
