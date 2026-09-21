<?php

namespace App\Modules\Admin\Loyalty\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncLoyaltyCompanyRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.tier_id' => ['required', 'integer', 'exists:loyalty_tiers,id'],
            'rates.*.service_type' => ['required', 'string', Rule::in(Order::serviceTypes())],
            'rates.*.company_key' => ['required', 'string', 'max:80'],
            'rates.*.company_name' => ['nullable', 'string', 'max:160'],
            'rates.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rates.*.is_active' => ['sometimes', 'boolean'],
            'refresh_airlines' => ['sometimes', 'boolean'],
        ];
    }
}
