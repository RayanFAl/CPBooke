<?php

namespace App\Modules\Api\LinkedAccounts\Http\Requests;

use App\Models\LinkedAccountRequest;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexLinkedAccountRequestsRequest extends ApiFormRequest
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
            'status' => ['nullable', 'string', Rule::in(LinkedAccountRequest::statuses())],
        ];
    }
}
