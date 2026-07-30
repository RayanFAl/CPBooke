<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class EmailChangeVerifyRequest extends ApiFormRequest
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
        $length = max(4, (int) config('profile.otp_length', 6));

        return [
            'email' => ['required', 'email', 'max:191'],
            'otp' => ['required', 'string', 'size:'.$length],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
