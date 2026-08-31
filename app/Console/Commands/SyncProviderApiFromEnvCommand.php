<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Modules\Providers\Services\ProviderApiConfigFromEnvService;
use Illuminate\Console\Command;

class SyncProviderApiFromEnvCommand extends Command
{
    protected $signature = 'provider:sync-api-config {provider_key=booknow : Provider key to configure}';

    protected $description = 'Sync provider API credentials from .env into provider_api_configs';

    public function handle(ProviderApiConfigFromEnvService $configFromEnv): int
    {
        $provider = Provider::query()->where('key', $this->argument('provider_key'))->first();

        if ($provider === null) {
            $this->error('Provider not found: '.$this->argument('provider_key'));

            return self::FAILURE;
        }

        $config = $configFromEnv->syncForProvider($provider);

        if ($config === null) {
            $this->error('PROVIDER_API_BASE_URL and PROVIDER_API_TOKEN must be set in .env');

            return self::FAILURE;
        }

        $this->info('API config synced for '.$provider->name.' ('.$config->environment.').');

        return self::SUCCESS;
    }
}
