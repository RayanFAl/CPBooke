<?php

namespace App\Modules\Admin\Suppliers\Http\Requests;

use App\Models\ProviderApiConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProviderApiConfigRequest extends FormRequest
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
            'environment' => ['required', 'string', Rule::in(ProviderApiConfig::environments())],
            'base_url' => ['nullable', 'url', 'max:500'],
            'auth_type' => ['required', 'string', Rule::in(ProviderApiConfig::authTypes())],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'refresh_token' => ['nullable', 'string', 'max:2000'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'custom_headers' => ['nullable', 'array'],
            'custom_headers.*' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in([
                ProviderApiConfig::STATUS_ACTIVE,
                ProviderApiConfig::STATUS_DISABLED,
            ])],
            'confirm_production' => ['nullable', 'boolean'],
        ];
    }
}
