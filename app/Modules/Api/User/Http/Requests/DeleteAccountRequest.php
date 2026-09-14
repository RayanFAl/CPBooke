<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use App\Modules\Api\User\Services\CustomerAccountDeletionService;
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

        if ($user !== null && $user->trashed()) {
            return [];
        }

        $rules = [
            'mode' => [
                'required',
                'string',
                Rule::in([
                    CustomerAccountDeletionService::MODE_IMMEDIATE,
                    CustomerAccountDeletionService::MODE_SCHEDULED,
                ]),
            ],
        ];

        if ($user !== null && filled($user->google_id)) {
            $rules['confirmation'] = ['required', 'string', Rule::in(['DELETE'])];

            return $rules;
        }

        $rules['password'] = ['required', 'current_password:sanctum'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in' => 'Choose immediate or scheduled account deletion.',
            'confirmation.in' => 'Type DELETE to confirm account deletion.',
            'password.current_password' => 'The password is incorrect.',
        ];
    }
}
