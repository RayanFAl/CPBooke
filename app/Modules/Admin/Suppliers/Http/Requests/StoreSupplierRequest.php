<?php

namespace App\Modules\Admin\Suppliers\Http\Requests;

use App\Models\Provider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('website') === '') {
            $this->merge(['website' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('providers', 'key')],
            'status' => ['required', 'string', Rule::in([Provider::STATUS_ACTIVE, Provider::STATUS_INACTIVE])],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settlement_cycle' => ['required', 'string', Rule::in(Provider::settlementCycles())],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'default_currency' => ['required', 'string', 'size:3'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'integration_status' => ['required', 'string', Rule::in(Provider::integrationStatuses())],
            'contract_starts_at' => ['nullable', 'date'],
            'contract_ends_at' => ['nullable', 'date', 'after_or_equal:contract_starts_at'],
            'contract_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
