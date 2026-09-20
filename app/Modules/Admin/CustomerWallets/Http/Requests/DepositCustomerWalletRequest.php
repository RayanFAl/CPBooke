<?php

namespace App\Modules\Admin\CustomerWallets\Http\Requests;

use App\Models\CustomerWalletTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositCustomerWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'currency' => ['required', 'string', 'size:3', Rule::in($supported)],
            'reason' => ['nullable', 'string', Rule::in(CustomerWalletTransaction::adminCreditReasons())],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }
    }
}
