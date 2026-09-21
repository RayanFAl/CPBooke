<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use App\Modules\Loyalty\Support\LoyaltyResultsPromo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'results_promo' => ['sometimes', 'array'],
            'results_promo.enabled' => ['sometimes', 'boolean'],
            'results_promo.require_active_level' => ['sometimes', 'boolean'],
            'results_promo.title_ar' => ['nullable', 'string', 'max:160'],
            'results_promo.title_en' => ['nullable', 'string', 'max:160'],
            'results_promo.body_ar' => ['nullable', 'string', 'max:500'],
            'results_promo.body_en' => ['nullable', 'string', 'max:500'],
            'results_promo.cta_ar' => ['nullable', 'string', 'max:80'],
            'results_promo.cta_en' => ['nullable', 'string', 'max:80'],
            'results_promo.action_type' => ['nullable', 'string', Rule::in(LoyaltyResultsPromo::ACTION_TYPES)],
            'results_promo.action_value' => ['nullable', 'string', 'max:255'],
            'results_promo.flights' => ['sometimes', 'array'],
            'results_promo.flights.body_ar' => ['nullable', 'string', 'max:500'],
            'results_promo.flights.body_en' => ['nullable', 'string', 'max:500'],
            'results_promo.hotels' => ['sometimes', 'array'],
            'results_promo.hotels.body_ar' => ['nullable', 'string', 'max:500'],
            'results_promo.hotels.body_en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
