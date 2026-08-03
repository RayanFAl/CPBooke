<?php

namespace App\Modules\Api\Auth\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class ForgotPasswordRequest extends ApiFormRequest
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
            'email' => ['required', 'email', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => strtolower(trim($this->input('email'))),
            ]);
        }
    }
}
