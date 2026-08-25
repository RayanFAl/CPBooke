<?php

namespace App\Modules\Providers\Services;

use App\Models\ProviderApiConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProviderConnectionTestService
{
    public function __construct(
        private readonly ProviderApiLogService $providerApiLogService,
    ) {
    }

    public function test(ProviderApiConfig $config): array
    {
        if ($config->status === ProviderApiConfig::STATUS_DISABLED) {
            throw ValidationException::withMessages([
                'config' => 'This API configuration is disabled.',
            ]);
        }

        if (! filled($config->base_url)) {
            throw ValidationException::withMessages([
                'base_url' => 'Base URL is required before testing the connection.',
            ]);
        }

        $url = $this->buildTestUrl($config);
        $timeout = max(1, (int) ($config->timeout ?? config('provider_api.default_timeout', 30)));
        $started = microtime(true);

        try {
            $pending = Http::timeout($timeout)
                ->withHeaders($this->buildHeaders($config));

            $pending = $this->applyAuthentication($pending, $config);

            $response = $pending->get($url);
            $latencyMs = (int) max(0, round((microtime(true) - $started) * 1000));
            $success = $response->successful();
            $message = $success
                ? 'Connection successful.'
                : $this->safeFailureMessage($response->status(), $response->body());

            $config->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => $success ? ProviderApiConfig::TEST_STATUS_SUCCESS : ProviderApiConfig::TEST_STATUS_FAILED,
                'last_test_http_status' => $response->status(),
                'last_test_message' => $message,
                'last_test_latency_ms' => $latencyMs,
            ])->save();

            $this->providerApiLogService->record(
                provider: $config->provider,
                endpointKey: 'provider.connection_test',
                statusCode: $response->status(),
                success: $success,
                responseTimeMs: $latencyMs,
                requestBody: ['url' => $url, 'environment' => $config->environment],
                responseBody: $this->safeJsonBody($response->body()),
                errorMessage: $success ? null : $message,
                correlationId: 'CP-CONN-'.strtoupper(Str::random(5)),
                referenceType: 'provider_api_config',
                referenceId: (string) $config->id,
                context: [
                    'provider_key' => $config->provider?->key,
                    'environment' => $config->environment,
                ],
            );

            return [
                'success' => $success,
                'http_status' => $response->status(),
                'latency_ms' => $latencyMs,
                'environment' => $config->environment,
                'message' => $message,
            ];
        } catch (ConnectionException $exception) {
            $latencyMs = (int) max(0, round((microtime(true) - $started) * 1000));
            $message = 'Connection failed: timeout or unreachable host.';

            $config->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => ProviderApiConfig::TEST_STATUS_FAILED,
                'last_test_http_status' => null,
                'last_test_message' => $message,
                'last_test_latency_ms' => $latencyMs,
            ])->save();

            $this->providerApiLogService->record(
                provider: $config->provider,
                endpointKey: 'provider.connection_test',
                statusCode: null,
                success: false,
                responseTimeMs: $latencyMs,
                requestBody: ['url' => $url, 'environment' => $config->environment],
                responseBody: null,
                errorMessage: $message,
                correlationId: 'CP-CONN-'.strtoupper(Str::random(5)),
                referenceType: 'provider_api_config',
                referenceId: (string) $config->id,
                context: [
                    'provider_key' => $config->provider?->key,
                    'environment' => $config->environment,
                ],
            );

            return [
                'success' => false,
                'http_status' => null,
                'latency_ms' => $latencyMs,
                'environment' => $config->environment,
                'message' => $message,
            ];
        }
    }

    private function buildTestUrl(ProviderApiConfig $config): string
    {
        $base = rtrim((string) $config->base_url, '/');
        $path = (string) config('provider_api.connection_test_path', '/');

        if ($path === '' || $path === '/') {
            return $base;
        }

        return $base.'/'.ltrim($path, '/');
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

    private function safeFailureMessage(int $status, string $body): string
    {
        if ($status === 401 || $status === 403) {
            return 'Authentication failed.';
        }

        if ($status >= 500) {
            return 'Provider returned a server error.';
        }

        $trimmed = trim(Str::limit(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '', 120));

        if ($trimmed === '') {
            return 'Connection failed with HTTP status '.$status.'.';
        }

        return 'Connection failed: '.$trimmed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeJsonBody(string $body): ?array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
