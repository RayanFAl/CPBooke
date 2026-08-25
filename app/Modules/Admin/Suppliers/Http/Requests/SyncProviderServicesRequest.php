<?php

namespace App\Modules\Admin\Suppliers\Http\Requests;

use App\Models\ProviderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProviderServicesRequest extends FormRequest
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
            'services' => ['required', 'array'],
            'services.*.service' => ['required', 'string', Rule::in(ProviderService::serviceKeys())],
            'services.*.enabled' => ['nullable', 'boolean'],
            'services.*.configuration' => ['nullable', 'array'],
        ];
    }
}
