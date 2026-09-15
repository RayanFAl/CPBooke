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
            'usd_buy_rate_to_lyd' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99999999'],
            'usd_sell_rate_to_lyd' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99999999'],
            'eur_buy_rate_to_lyd' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99999999'],
            'eur_sell_rate_to_lyd' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99999999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $fields = [
                'usd_buy_rate_to_lyd',
                'usd_sell_rate_to_lyd',
                'eur_buy_rate_to_lyd',
                'eur_sell_rate_to_lyd',
            ];

            $hasAny = false;
            foreach ($fields as $field) {
                $value = $this->input($field);
                if ($value !== null && $value !== '') {
                    $hasAny = true;
                    break;
                }
            }

            if (! $hasAny) {
                $validator->errors()->add(
                    'usd_buy_rate_to_lyd',
                    'Provide at least one buy/sell rate to update (USD or EUR).'
                );
            }

            foreach (['usd', 'eur'] as $prefix) {
                $buy = $this->input("{$prefix}_buy_rate_to_lyd");
                $sell = $this->input("{$prefix}_sell_rate_to_lyd");

                if ($buy !== null && $buy !== '' && $sell !== null && $sell !== '' && (float) $sell < (float) $buy) {
                    $validator->errors()->add(
                        "{$prefix}_sell_rate_to_lyd",
                        strtoupper($prefix).' sell rate must be greater than or equal to buy rate.'
                    );
                }
            }
        });
    }
}
