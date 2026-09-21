<?php

use App\Models\Provider;
use App\Modules\Admin\Loyalty\Services\LoyaltyCompanyRateAdminService;
use App\Modules\Loyalty\Services\LoyaltyAirlinesCatalogService;
use App\Modules\Providers\Services\ProviderApiConfigFromEnvService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$provider = Provider::query()->where('key', Provider::KEY_BOOKNOW)->first()
    ?? Provider::query()->orderBy('id')->first();

if ($provider === null) {
    fwrite(STDERR, "no_provider\n");
    exit(1);
}

$config = app(ProviderApiConfigFromEnvService::class)->syncForProvider($provider);

echo 'provider='.$provider->key.PHP_EOL;
echo 'base='.($config?->base_url ?? 'null').PHP_EOL;
echo 'has_token='.(filled($config?->access_token) ? 'yes' : 'no').PHP_EOL;

$catalog = app(LoyaltyAirlinesCatalogService::class);
$catalog->forgetCache();
$result = $catalog->fetch();

echo 'source='.($result['source'] ?? 'null').PHP_EOL;
echo 'error='.($result['error'] ?? 'null').PHP_EOL;
echo 'count='.count($result['airlines']).PHP_EOL;

foreach ($result['airlines'] as $airline) {
    echo $airline['code'].' | '.$airline['name'].' | '.($airline['logo_url'] ?? '').PHP_EOL;
}

$matrix = app(LoyaltyCompanyRateAdminService::class)->matrix(refreshAirlines: true);

echo 'companies='.count($matrix['companies']).PHP_EOL;
echo 'with_logo='.collect($matrix['companies'])->whereNotNull('logo_url')->count().PHP_EOL;
echo 'rates='.count($matrix['rates']).PHP_EOL;
