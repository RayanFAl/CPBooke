<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoyaltyTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'monthly_spend' => ['nullable', 'numeric', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'duration_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'duration_unit' => ['nullable', 'string', 'in:days,months'],
            'is_active' => ['sometimes', 'boolean'],
            'notify_customers' => ['sometimes', 'boolean'],
        ];
    }
}
