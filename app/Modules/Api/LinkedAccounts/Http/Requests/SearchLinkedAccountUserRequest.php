<?php

namespace App\Modules\Api\LinkedAccounts\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class SearchLinkedAccountUserRequest extends ApiFormRequest
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
            'identifier' => ['required', 'string', 'max:191'],
        ];
    }
}
