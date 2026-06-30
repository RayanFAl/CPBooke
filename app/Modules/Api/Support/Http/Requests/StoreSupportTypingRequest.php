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
            'agent_typing' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function hasTypingInput(): bool
    {
        return $this->exists('typing') || $this->exists('agent_typing');
    }

    public function resolveTypingInput(): ?bool
    {
        if ($this->exists('typing')) {
            return $this->boolean('typing');
        }

        if ($this->exists('agent_typing')) {
            return $this->boolean('agent_typing');
        }

        return null;
    }

    public function resolvedTyping(bool $default = true): bool
    {
        return $this->resolveTypingInput() ?? $default;
    }
}