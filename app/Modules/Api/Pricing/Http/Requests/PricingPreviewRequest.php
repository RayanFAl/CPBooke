<?php

namespace App\Modules\Api\Pricing\Http\Requests;

use App\Models\Order;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class PricingPreviewRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => strtoupper($this->input('currency')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_type' => ['required', 'string', Rule::in(Order::serviceTypes())],
            'currency' => ['nullable', 'string', 'size:3'],
            'base_amount' => ['required', 'numeric', 'min:0.01'],
            'provider_name' => ['nullable', 'string', 'max:120'],
            'attributes' => ['nullable', 'array'],
        ];
    }
}