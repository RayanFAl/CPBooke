<?php

namespace App\Modules\Admin\ProviderWallets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('providers', 'key')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
