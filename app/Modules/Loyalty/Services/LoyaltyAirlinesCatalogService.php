<?php

namespace App\Modules\Loyalty\Services;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Modules\Providers\Services\ProviderApiConfigFromEnvService;
use App\Modules\Providers\Services\ProviderApiHttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoyaltyAirlinesCatalogService
{
    /**
     * @var array<int, string>
     */
    private const PATHS = [
        '/v1/airlines',
        '/api/v1/airlines',
        '/v1/air/airlines',
        '/api/v1/air/airlines',
        '/agency/median/api/v1/airlines',
        '/agency/median/api/v1/air/airlines',
    ];

    public function __construct(
        private readonly ProviderApiHttpClient $httpClient,
        private readonly ProviderApiConfigFromEnvService $configFromEnv,
    ) {}

    /**
     * @return array{
     *     airlines: array<int, array{code: string, name: string, logo_url: string}>,
     *     source: string|null,
     *     error: string|null
     * }
     */
    public function fetch(): array
    {
        $cacheKey = 'loyalty.airlines.catalog.v1';
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['airlines']) && is_array($cached['airlines'])) {
            return [
                'airlines' => $cached['airlines'],
                'source' => $cached['source'] ?? 'cache',
                'error' => null,
            ];
        }

        $config = $this->resolveApiConfig();

        if ($config === null) {
            return [
                'airlines' => [],
                'source' => null,
                'error' => 'Provider API is not configured for airlines.',
            ];
        }

        foreach (self::PATHS as $path) {
            try {
                $response = $this->httpClient->get($config, $path);
                $payload = $response->json();

                if (! $response->successful() || ! is_array($payload)) {
                    continue;
                }

                $airlines = $this->normalizeAirlines($payload);

                if ($airlines === []) {
                    continue;
                }

                $result = [
                    'airlines' => $airlines,
                    'source' => $path,
                    'error' => null,
                ];

                Cache::put($cacheKey, $result, now()->addHours(6));

                return $result;
            } catch (\Throwable $exception) {
                Log::warning('loyalty.airlines.fetch_failed', [
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'airlines' => [],
            'source' => null,
            'error' => 'Unable to load airlines from the provider API.',
        ];
    }

    public function forgetCache(): void
    {
        Cache::forget('loyalty.airlines.catalog.v1');
    }

    private function resolveApiConfig(): ?ProviderApiConfig
    {
        $provider = Provider::query()
            ->where('key', Provider::KEY_BOOKNOW)
            ->first()
            ?? Provider::query()->where('status', Provider::STATUS_ACTIVE)->orderBy('id')->first();

        if ($provider === null) {
            return null;
        }

        $environment = strtolower((string) config('provider_api.environment', ProviderApiConfig::ENVIRONMENT_PRODUCTION));

        $config = ProviderApiConfig::query()
            ->where('provider_id', $provider->id)
            ->where('environment', $environment)
            ->where('status', ProviderApiConfig::STATUS_ACTIVE)
            ->whereNotNull('base_url')
            ->first();

        if ($config !== null) {
            return $config;
        }

        return $this->configFromEnv->syncForProvider($provider);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{code: string, name: string, logo_url: string}>
     */
    private function normalizeAirlines(array $payload): array
    {
        $rows = $payload['data'] ?? $payload['airlines'] ?? $payload;

        if (! is_array($rows)) {
            return [];
        }

        // Nested { data: { airlines: [...] } }
        if (isset($rows['airlines']) && is_array($rows['airlines'])) {
            $rows = $rows['airlines'];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = $row['code']
                ?? $row['iata']
                ?? $row['iata_code']
                ?? $row['airline_code']
                ?? $row['id']
                ?? null;

            if (! is_string($code) && ! is_numeric($code)) {
                continue;
            }

            $code = strtoupper(trim((string) $code));

            if ($code === '') {
                continue;
            }

            $name = $row['name']
                ?? $row['airline_name']
                ?? $row['title']
                ?? $code;

            $logoUrl = $row['logo_url']
                ?? $row['logo']
                ?? null;

            if (! is_string($logoUrl) || trim($logoUrl) === '') {
                $logoUrl = $this->defaultLogoUrl($code);
            }

            $normalized[$code] = [
                'code' => $code,
                'name' => is_string($name) && trim($name) !== '' ? trim($name) : $code,
                'logo_url' => trim((string) $logoUrl),
            ];
        }

        ksort($normalized);

        return array_values($normalized);
    }

    private function defaultLogoUrl(string $code): string
    {
        $baseUrl = rtrim((string) config('provider_api.base_url', 'https://agency.atom.ly/agency/median/api'), '/');

        return "{$baseUrl}/v1/airlines/{$code}/logo";
    }
}
