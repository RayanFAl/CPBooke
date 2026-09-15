<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Seed LYD/USD/EUR with buy + sell rates (parallel-market style defaults).
     */
    public function run(): void
    {
        $defaults = [
            ExchangeRate::CURRENCY_LYD => [
                'buy' => '1.00000000',
                'sell' => '1.00000000',
            ],
            // Approx. parallel market with a small spread around recent mid levels.
            ExchangeRate::CURRENCY_USD => [
                'buy' => '9.35000000',
                'sell' => '9.42000000',
            ],
            ExchangeRate::CURRENCY_EUR => [
                'buy' => '10.85000000',
                'sell' => '10.93000000',
            ],
        ];

        foreach ($defaults as $code => $pair) {
            $mid = number_format(((float) $pair['buy'] + (float) $pair['sell']) / 2, 8, '.', '');

            ExchangeRate::query()->updateOrCreate(
                ['currency_code' => $code],
                [
                    'buy_rate_to_lyd' => $pair['buy'],
                    'sell_rate_to_lyd' => $pair['sell'],
                    'rate_to_lyd' => $mid,
                    'is_active' => true,
                ],
            );
        }
    }
}
