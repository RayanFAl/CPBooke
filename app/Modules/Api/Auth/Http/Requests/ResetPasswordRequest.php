<?php

namespace App\Modules\Api\Auth\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends ApiFormRequest
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
            'reset_token' => ['required', 'string', 'min:32', 'max:128'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
