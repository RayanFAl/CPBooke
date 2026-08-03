<?php

namespace App\Modules\Admin\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('default_currency') && is_string($this->input('default_currency'))) {
            $merge['default_currency'] = strtoupper(trim($this->input('default_currency')));
        }

        if ($this->has('locale') && is_string($this->input('locale'))) {
            $merge['locale'] = strtolower(trim($this->input('locale')));
        }

        foreach ([
            'channel_email_enabled',
            'channel_sms_enabled',
            'channel_whatsapp_enabled',
            'channel_push_enabled',
            'feature_maintenance_mode',
            'feature_chat_enabled',
            'feature_legacy_order_create',
        ] as $booleanField) {
            if ($this->has($booleanField)) {
                $merge[$booleanField] = filter_var($this->input($booleanField), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->boolean($booleanField);
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'company_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_currency' => ['sometimes', 'required', 'string', 'size:3'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'required', 'string', 'max:16'],
            'default_commission_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'channel_email_enabled' => ['sometimes', 'boolean'],
            'channel_sms_enabled' => ['sometimes', 'boolean'],
            'channel_whatsapp_enabled' => ['sometimes', 'boolean'],
            'channel_push_enabled' => ['sometimes', 'boolean'],
            'email_from_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sms_sender_name' => ['sometimes', 'nullable', 'string', 'max:40'],
            'whatsapp_sender_name' => ['sometimes', 'nullable', 'string', 'max:40'],
            'feature_maintenance_mode' => ['sometimes', 'boolean'],
            'feature_chat_enabled' => ['sometimes', 'boolean'],
            'feature_legacy_order_create' => ['sometimes', 'boolean'],
            'settings_version' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
            'section' => ['sometimes', 'string', Rule::in([
                'company',
                'localization',
                'margins',
                'channels',
                'features',
            ])],
        ];
    }
}
