<?php

namespace App\Modules\Admin\ProviderWallets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustProviderWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
