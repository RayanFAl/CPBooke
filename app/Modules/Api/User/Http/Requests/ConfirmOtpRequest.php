<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class ConfirmOtpRequest extends ApiFormRequest
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
            'otp' => ['required', 'string', 'size:'.$length],
        ];
    }
}
