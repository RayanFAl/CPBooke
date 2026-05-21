<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tierId = $this->route('loyaltyTier')?->id;

        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('loyalty_tiers', 'code')->ignore($tierId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'badge_label' => ['nullable', 'string', 'max:120'],
            'color_token' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}