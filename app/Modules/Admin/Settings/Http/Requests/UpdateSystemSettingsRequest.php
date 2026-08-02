<?php

namespace App\Modules\Admin\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.manage');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'company_display_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:191'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'default_currency' => ['required', 'string', 'size:3', Rule::in(['LYD', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'TND', 'EGP'])],
            'timezone' => ['required', 'timezone:all'],
            'default_locale' => ['required', 'string', 'max:12'],
            'default_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'email_enabled' => ['required', 'boolean'],
            'sms_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'push_enabled' => ['required', 'boolean'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'sms_sender_id' => ['nullable', 'string', 'max:40'],
            'maintenance_mode' => ['required', 'boolean'],
            'support_chat_enabled' => ['required', 'boolean'],
            'orders_legacy_create_enabled' => ['required', 'boolean'],
            'home_offers_enabled' => ['required', 'boolean'],
        ];
    }
}
