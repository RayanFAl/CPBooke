<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$provider = App\Models\Provider::query()->first();

if ($provider === null) {
    echo "no provider\n";
    exit(1);
}

$config = App\Models\ProviderApiConfig::query()->where('provider_id', $provider->id)->first();
echo 'provider: '.$provider->name.' ('.$provider->key.")\n";
echo 'config rows: '.App\Models\ProviderApiConfig::query()->count()."\n";

if ($config !== null) {
    echo 'base_url: '.$config->base_url."\n";
    echo 'auth_type: '.$config->auth_type."\n";
    echo 'has_token: '.(filled($config->access_token) ? 'yes' : 'no')."\n";
}

$tenant = config('wallets.provider_balance.tenant');
$path = str_replace('{tenant}', (string) $tenant, (string) config('wallets.provider_balance.path'));
$base = rtrim((string) config('provider_api.base_url'), '/');
echo 'request_url: '.$base.'/'.ltrim($path, '/')."\n";

try {
    $result = app(App\Modules\Wallets\Services\ProviderWalletBalanceQueryService::class)
        ->fetchForProvider($provider);

    echo json_encode($result, JSON_PRETTY_PRINT)."\n";
} catch (Throwable $exception) {
    echo 'exception: '.get_class($exception).': '.$exception->getMessage()."\n";
}

if ($config !== null) {
    try {
        $response = app(App\Modules\Providers\Services\ProviderApiHttpClient::class)->get($config, $path);
        echo 'http_status: '.$response->status()."\n";
        echo 'http_body: '.substr((string) $response->body(), 0, 500)."\n";
    } catch (Throwable $exception) {
        echo 'http_exception: '.get_class($exception).': '.$exception->getMessage()."\n";
    }
}
