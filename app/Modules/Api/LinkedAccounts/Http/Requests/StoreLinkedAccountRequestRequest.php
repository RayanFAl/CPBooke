<?php

namespace App\Modules\Api\LinkedAccounts\Http\Requests;

use App\Models\LinkedAccount;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreLinkedAccountRequestRequest extends ApiFormRequest
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
            'to_user' => ['required', 'string', 'max:191'],
            'relationship_type' => ['required', 'string', Rule::in(LinkedAccount::relationshipTypes())],
            'nickname' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
