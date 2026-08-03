<?php

namespace App\Modules\Admin\ProviderWallets\Http\Requests;

use App\Models\ProviderWallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderWalletRequest extends FormRequest
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
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'environment' => ['required', 'string', Rule::in(config('wallets.environments', [
                ProviderWallet::ENVIRONMENT_PRODUCTION,
                ProviderWallet::ENVIRONMENT_SANDBOX,
            ]))],
            'low_balance_threshold' => ['nullable', 'numeric'],
            'allow_negative' => ['sometimes', 'boolean'],
        ];
    }
}
