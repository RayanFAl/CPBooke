<?php

namespace App\Modules\Admin\CustomerWallets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DebitCustomerWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
