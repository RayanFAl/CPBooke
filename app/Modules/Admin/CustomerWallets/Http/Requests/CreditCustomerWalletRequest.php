<?php

namespace App\Modules\Admin\CustomerWallets\Http\Requests;

use App\Models\CustomerWalletTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditCustomerWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'reason' => ['required', 'string', Rule::in(CustomerWalletTransaction::adminCreditReasons())],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
