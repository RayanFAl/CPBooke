<?php

namespace App\Modules\Api\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supported = config('customer_wallets.supported_currencies', ['LYD', 'USD', 'EUR']);

        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', Rule::in($supported)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }
    }
}
