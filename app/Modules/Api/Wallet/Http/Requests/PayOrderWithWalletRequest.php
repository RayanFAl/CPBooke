<?php

namespace App\Modules\Api\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayOrderWithWalletRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ];
    }
}
