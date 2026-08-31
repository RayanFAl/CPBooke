<?php

namespace App\Modules\Providers\Services;

use App\Models\ProviderApiConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ProviderApiHttpClient
{
    public function get(ProviderApiConfig $config, string $path): Response
    {
        $url = rtrim((string) $config->base_url, '/').'/'.ltrim($path, '/');
        $timeout = max(1, (int) ($config->timeout ?? config('provider_api.default_timeout', 30)));

        $pending = Http::timeout($timeout)
            ->withOptions(['verify' => $this->sslVerifyOption()])
            ->withHeaders($this->buildHeaders($config));

        $pending = $this->applyAuthentication($pending, $config);

        return $pending->get($url);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(ProviderApiConfig $config): array
    {
        $headers = [];

        foreach ($config->custom_headers ?? [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $headers[$key] = (string) $value;
            }
        }

        return $headers;
    }

    private function applyAuthentication(\Illuminate\Http\Client\PendingRequest $pending, ProviderApiConfig $config): \Illuminate\Http\Client\PendingRequest
    {
        return match ($config->auth_type) {
            ProviderApiConfig::AUTH_BEARER_TOKEN => filled($config->access_token)
                ? $pending->withToken((string) $config->access_token)
                : $pending,
            ProviderApiConfig::AUTH_API_KEY => filled($config->api_key)
                ? $pending->withHeaders([
                    (string) config('provider_api.api_key_header', 'X-API-Key') => (string) $config->api_key,
                ])
                : $pending,
            ProviderApiConfig::AUTH_API_KEY_SECRET => $pending->withHeaders(array_filter([
                (string) config('provider_api.api_key_header', 'X-API-Key') => $config->api_key,
                'X-API-Secret' => $config->api_secret,
            ])),
            ProviderApiConfig::AUTH_OAUTH2 => filled($config->access_token)
                ? $pending->withToken((string) $config->access_token)
                : $pending,
            default => $pending,
        };
    }

    private function sslVerifyOption(): bool|string
    {
        $configured = config('provider_api.ssl_verify');

        if ($configured === false || $configured === 'false' || $configured === '0') {
            return app()->environment('local') ? false : true;
        }

        if ($configured === true || $configured === 'true' || $configured === '1') {
            return true;
        }

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        if (app()->environment('local') && ($configured === null || $configured === '')) {
            return false;
        }

        $bundle = config('provider_api.ca_bundle');
        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            return $bundle;
        }

        return true;
    }
}
