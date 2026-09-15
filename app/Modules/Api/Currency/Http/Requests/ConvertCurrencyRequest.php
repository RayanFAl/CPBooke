<?php

namespace App\Modules\Api\Currency\Http\Requests;

use App\Models\ExchangeRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertCurrencyRequest extends FormRequest
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
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'from' => ['required', 'string', 'size:3', Rule::in(ExchangeRate::SUPPORTED_CURRENCIES)],
            'to' => ['required', 'string', 'size:3', Rule::in(ExchangeRate::SUPPORTED_CURRENCIES)],
            'side' => ['sometimes', 'nullable', 'string', Rule::in(ExchangeRate::CONVERSION_SIDES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('from')) {
            $this->merge(['from' => ExchangeRate::normalizeCode((string) $this->input('from'))]);
        }

        if ($this->has('to')) {
            $this->merge(['to' => ExchangeRate::normalizeCode((string) $this->input('to'))]);
        }

        if ($this->has('side') && is_string($this->input('side'))) {
            $this->merge(['side' => strtolower(trim((string) $this->input('side')))]);
        }
    }
}
