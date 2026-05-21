<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoyaltySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_currency') && is_string($this->input('default_currency'))) {
            $this->merge([
                'default_currency' => strtoupper($this->input('default_currency')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'loyalty_enabled' => ['sometimes', 'boolean'],
            'auto_upgrade_enabled' => ['sometimes', 'boolean'],
            'auto_downgrade_enabled' => ['sometimes', 'boolean'],
            'visible_in_mobile_app' => ['sometimes', 'boolean'],
            'allow_discount_stacking' => ['sometimes', 'boolean'],
            'max_global_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_discountable_order_amount' => ['nullable', 'numeric', 'min:0'],
            'default_currency' => ['required', 'string', 'size:3'],
            'settings_version' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}