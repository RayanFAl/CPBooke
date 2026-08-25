<?php

namespace App\Modules\Providers\Services;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Models\ProviderService;

class ProviderApiConfigPresenter
{
  /**
   * @return array<string, mixed>
   */
  public function serializeConfig(ProviderApiConfig $config, bool $includeCredentialAudit = false): array
  {
    $masked = config('provider_api.masked_secret', '••••••••••••');

    return [
      'id' => $config->id,
      'provider_id' => $config->provider_id,
      'environment' => $config->environment,
      'is_production' => $config->isProduction(),
      'base_url' => $config->base_url,
      'auth_type' => $config->auth_type,
      'api_key' => $this->maskSecret($config->api_key, $masked),
      'api_secret' => $this->maskSecret($config->api_secret, $masked),
      'access_token' => $this->maskSecret($config->access_token, $masked),
      'refresh_token' => $this->maskSecret($config->refresh_token, $masked),
      'webhook_url' => $config->webhook_url,
      'timeout' => $config->timeout,
      'custom_headers' => $config->custom_headers ?? [],
      'status' => $config->status,
      'last_tested_at' => optional($config->last_tested_at)?->toIso8601String(),
      'last_test_status' => $config->last_test_status,
      'last_test_http_status' => $config->last_test_http_status,
      'last_test_message' => $config->last_test_message,
      'last_test_latency_ms' => $config->last_test_latency_ms,
      'has_api_key' => filled($config->api_key),
      'has_api_secret' => filled($config->api_secret),
      'has_access_token' => filled($config->access_token),
      'has_refresh_token' => filled($config->refresh_token),
      'credentials_visible' => $includeCredentialAudit,
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function serializeServices(Provider $provider): array
  {
    $existing = $provider->providerServices->keyBy('service');
    $labels = ProviderService::serviceLabels();

    return collect($labels)->map(function (string $label, string $service) use ($existing): array {
      $record = $existing->get($service);

      return [
        'service' => $service,
        'label' => $label,
        'enabled' => (bool) ($record?->enabled ?? false),
        'configuration' => $record?->configuration ?? [],
      ];
    })->values()->all();
  }

  private function maskSecret(?string $value, string $masked): ?string
  {
    if (! filled($value)) {
      return null;
    }

    return $masked;
  }
}
