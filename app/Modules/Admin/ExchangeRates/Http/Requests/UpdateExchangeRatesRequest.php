<?php

namespace App\Modules\Admin\ExchangeRates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exchange-rates.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usd_rate_to_lyd' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99999999'],
            'eur_rate_to_lyd' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usd_rate_to_lyd.gt' => 'The USD rate must be greater than zero.',
            'eur_rate_to_lyd.gt' => 'The EUR rate must be greater than zero.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $usd = $this->input('usd_rate_to_lyd');
            $eur = $this->input('eur_rate_to_lyd');

            if (($usd === null || $usd === '') && ($eur === null || $eur === '')) {
                $validator->errors()->add(
                    'usd_rate_to_lyd',
                    'Provide at least one exchange rate to update (USD or EUR).'
                );
            }
        });
    }
}
