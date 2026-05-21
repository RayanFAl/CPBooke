<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use App\Models\LoyaltyBenefit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'benefit_type' => ['required', 'string', Rule::in([
                LoyaltyBenefit::TYPE_DISCOUNT,
                LoyaltyBenefit::TYPE_SUPPORT,
                LoyaltyBenefit::TYPE_OFFER,
                LoyaltyBenefit::TYPE_UPGRADE,
                LoyaltyBenefit::TYPE_SERVICE,
            ])],
            'value_type' => ['nullable', 'string', Rule::in([
                LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                LoyaltyBenefit::VALUE_TYPE_FIXED,
                LoyaltyBenefit::VALUE_TYPE_FLAG,
                LoyaltyBenefit::VALUE_TYPE_TEXT,
            ])],
            'value' => ['nullable', 'numeric', 'min:0'],
            'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_highlighted' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}