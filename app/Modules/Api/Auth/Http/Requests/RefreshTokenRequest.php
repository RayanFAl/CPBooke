<?php

namespace App\Modules\Api\Auth\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class RefreshTokenRequest extends ApiFormRequest
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
            'refresh_token' => ['required', 'string', 'min:40', 'max:128'],
        ];
    }
}
