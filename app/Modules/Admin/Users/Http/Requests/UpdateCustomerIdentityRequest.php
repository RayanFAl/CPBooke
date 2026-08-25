<?php

namespace App\Modules\Admin\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('phone') === '') {
            $this->merge(['phone' => null]);
        }

        if ($this->input('country') === '') {
            $this->merge(['country' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }
}
