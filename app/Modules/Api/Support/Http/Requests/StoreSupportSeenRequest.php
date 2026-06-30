<?php

namespace App\Modules\Api\Support\Http\Requests;

class StoreSupportSeenRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isCustomerAccount() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['nullable', 'array'],
            'message_ids.*' => ['integer', 'exists:support_messages,id'],
        ];
    }
}