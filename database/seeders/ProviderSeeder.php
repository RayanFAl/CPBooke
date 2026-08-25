<?php

namespace Database\Seeders;

use App\Models\Provider;
use App\Models\ProviderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProviderSeeder extends Seeder
{
    /**
     * Seed BookNow as the single supplier for every product.
     */
    public function run(): void
    {
        $provider = Provider::query()->updateOrCreate(
            ['key' => Provider::KEY_BOOKNOW],
            [
                'name' => 'BookNow',
                'legal_name' => 'BookNow Travel Services',
                'status' => Provider::STATUS_ACTIVE,
                'commission_rate' => '10.00',
                'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
                'default_currency' => 'LYD',
                'integration_status' => Provider::INTEGRATION_LIVE,
                'notes' => 'Single BookNow supplier for flights, hotels, insurance, eSIM, visa, activities, and transfers.',
            ],
        );

        $this->enableAllServices($provider);
        $this->retireSplitBookNowProviders();
    }

    private function enableAllServices(Provider $provider): void
    {
        if (! Schema::hasTable('provider_services')) {
            return;
        }

        foreach (ProviderService::serviceKeys() as $service) {
            ProviderService::query()->updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'service' => $service,
                ],
                [
                    'enabled' => true,
                ],
            );
        }
    }

    /**
     * Previous seeds created separate BookNow rows per product. Keep one supplier.
     */
    private function retireSplitBookNowProviders(): void
    {
        Provider::query()
            ->whereIn('key', [
                Provider::KEY_BOOKNOW_HOTELS,
                Provider::KEY_BOOKNOW_ESIM,
                Provider::KEY_BOOKNOW_INSURANCE,
            ])
            ->get()
            ->each(function (Provider $provider): void {
                $hasRelatedRecords = $provider->orders()->exists()
                    || $provider->wallets()->exists()
                    || $provider->apiConfigs()->exists();

                if ($hasRelatedRecords) {
                    $provider->forceFill([
                        'status' => Provider::STATUS_INACTIVE,
                        'integration_status' => Provider::INTEGRATION_PAUSED,
                        'notes' => 'Retired: BookNow is the single supplier for all products.',
                    ])->save();

                    return;
                }

                $provider->providerServices()->delete();
                $provider->delete();
            });
    }
}
