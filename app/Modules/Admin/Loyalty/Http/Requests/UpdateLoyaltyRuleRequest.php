<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use App\Models\LoyaltyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'rule_type' => ['required', 'string', Rule::in([LoyaltyRule::TYPE_UPGRADE])],
            'min_completed_orders' => ['required', 'integer', 'min:0'],
            'min_lifetime_spend' => ['required', 'numeric', 'min:0'],
            'min_period_orders' => ['required', 'integer', 'min:0'],
            'min_period_spend' => ['required', 'numeric', 'min:0'],
            'period_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'allow_downgrade' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }
}