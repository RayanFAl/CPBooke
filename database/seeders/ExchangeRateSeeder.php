<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Seed the three supported currencies. LYD is fixed at 1;
     * USD/EUR defaults match the product examples and are admin-editable.
     */
    public function run(): void
    {
        // Approximate Libya parallel-market mid rates (cash), early/mid Sep 2026.
        // Admins can change these anytime from Exchange Rates settings.
        $defaults = [
            ExchangeRate::CURRENCY_LYD => '1.00000000',
            ExchangeRate::CURRENCY_USD => '9.38500000',
            ExchangeRate::CURRENCY_EUR => '10.89000000',
        ];

        foreach ($defaults as $code => $rate) {
            ExchangeRate::query()->updateOrCreate(
                ['currency_code' => $code],
                [
                    'rate_to_lyd' => $rate,
                    'is_active' => true,
                ],
            );
        }
    }
}
