<?php

namespace App\Modules\Api\Support\Http\Requests;

class StoreSupportTypingRequest extends ApiFormRequest
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
            'typing' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}