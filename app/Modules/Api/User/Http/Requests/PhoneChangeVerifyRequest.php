<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class PhoneChangeVerifyRequest extends ApiFormRequest
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
            'phone' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'string', 'size:'.$length],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => trim($this->input('phone'))]);
        }
    }
}
