<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        Provider::query()->updateOrCreate(
            ['key' => Provider::KEY_BOOKNOW],
            [
                'name' => 'BookNow',
                'legal_name' => 'BookNow Travel Services',
                'status' => Provider::STATUS_ACTIVE,
                'commission_rate' => '10.00',
                'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
                'default_currency' => 'LYD',
                'integration_status' => Provider::INTEGRATION_LIVE,
                'notes' => 'Default flight supplier for mobile sync-flight bookings.',
            ],
        );

        Provider::query()->updateOrCreate(
            ['key' => Provider::KEY_BOOKNOW_HOTELS],
            [
                'name' => 'BookNow Hotels',
                'legal_name' => 'BookNow Hotel Services',
                'status' => Provider::STATUS_ACTIVE,
                'commission_rate' => '10.00',
                'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
                'default_currency' => 'LYD',
                'integration_status' => Provider::INTEGRATION_LIVE,
                'notes' => 'Default hotel supplier for mobile sync-hotel bookings.',
            ],
        );
    }
}
