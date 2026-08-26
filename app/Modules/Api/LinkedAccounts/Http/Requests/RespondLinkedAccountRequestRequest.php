<?php

namespace App\Modules\Api\LinkedAccounts\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class RespondLinkedAccountRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accept' => ['required', 'boolean'],
        ];
    }
}
