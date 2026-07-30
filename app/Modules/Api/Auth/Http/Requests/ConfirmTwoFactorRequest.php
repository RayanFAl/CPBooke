<?php

namespace App\Modules\Api\Auth\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class ConfirmTwoFactorRequest extends ApiFormRequest
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
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
