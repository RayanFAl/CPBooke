<?php

namespace App\Modules\Providers\Services;

use App\Models\Provider;
use App\Models\ProviderApiConfig;

class ProviderApiConfigFromEnvService
{
    public function syncForProvider(Provider $provider): ?ProviderApiConfig
    {
        $baseUrl = rtrim((string) config('provider_api.base_url', ''), '/');
        $token = trim((string) config('provider_api.access_token', ''));

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        $environment = strtolower((string) config('provider_api.environment', ProviderApiConfig::ENVIRONMENT_PRODUCTION));
        $authType = (string) config('provider_api.auth_type', ProviderApiConfig::AUTH_BEARER_TOKEN);

        return ProviderApiConfig::query()->updateOrCreate(
            [
                'provider_id' => $provider->id,
                'environment' => $environment,
            ],
            [
                'base_url' => $baseUrl,
                'auth_type' => $authType,
                'access_token' => $authType === ProviderApiConfig::AUTH_BEARER_TOKEN ? $token : null,
                'api_key' => $authType === ProviderApiConfig::AUTH_API_KEY ? $token : null,
                'status' => ProviderApiConfig::STATUS_ACTIVE,
                'timeout' => (int) config('provider_api.default_timeout', 30),
            ],
        );
    }
}
