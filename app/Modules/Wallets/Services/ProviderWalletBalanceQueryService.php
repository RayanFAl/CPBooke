<?php

namespace App\Modules\Wallets\Services;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Models\ProviderWallet;
use App\Modules\Providers\Services\ProviderApiConfigFromEnvService;
use App\Modules\Providers\Services\ProviderApiHttpClient;
use Illuminate\Validation\ValidationException;

class ProviderWalletBalanceQueryService
{
    public function __construct(
        private readonly ProviderApiHttpClient $httpClient,
        private readonly ProviderApiConfigFromEnvService $configFromEnv,
    ) {
    }

    public function canQuery(?Provider $provider, ?string $environment = null): bool
    {
        if ($provider === null) {
            return false;
        }

        return $this->resolveApiConfig($provider, $environment) !== null
            && $this->resolveAgencyTenant($provider) !== null;
    }

    /**
     * @return array{
     *     available: bool,
     *     error: string|null,
     *     wallet_count: int,
     *     wallets: array<int, array{currency: string, balance: string}>,
     *     fetched_at: string|null
     * }
     */
    public function fetchForProvider(Provider $provider, ?string $environment = null): array
    {
        $empty = [
            'available' => false,
            'error' => null,
            'wallet_count' => 0,
            'wallets' => [],
            'fetched_at' => null,
        ];

        $config = $this->resolveApiConfig($provider, $environment);
        $tenant = $this->resolveAgencyTenant($provider);

        if ($config === null || $tenant === null) {
            return [
                ...$empty,
                'error' => $this->configurationErrorMessage($config === null, $tenant === null),
            ];
        }

        $path = str_replace('{tenant}', (string) $tenant, (string) config('wallets.provider_balance.path'));

        try {
            $response = $this->httpClient->get($config, $path);
            $payload = $response->json();
            $success = $response->successful() && is_array($payload) && ($payload['success'] ?? false) === true;

            if (! $success) {
                return [
                    ...$empty,
                    'error' => $this->failureMessage($response->status(), is_array($payload) ? $payload : null),
                ];
            }

            $wallets = collect($payload['data'] ?? [])
                ->filter(fn ($row): bool => is_array($row) && isset($row['currency']))
                ->map(fn (array $row): array => [
                    'currency' => strtoupper((string) $row['currency']),
                    'balance' => number_format((float) ($row['balance'] ?? 0), 2, '.', ''),
                ])
                ->values()
                ->all();

            return [
                'available' => true,
                'error' => null,
                'wallet_count' => count($wallets),
                'wallets' => $wallets,
                'fetched_at' => now()->toIso8601String(),
            ];
        } catch (ValidationException $exception) {
            return [
                ...$empty,
                'error' => collect($exception->errors())->flatten()->first() ?: 'Unable to fetch provider wallet balances.',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            return [
                ...$empty,
                'error' => app()->environment('local')
                    ? 'Connection failed: '.$exception->getMessage()
                    : 'Unable to reach provider wallet API.',
            ];
        } catch (\Throwable $exception) {
            return [
                ...$empty,
                'error' => 'Unable to reach provider wallet API.',
            ];
        }
    }

    private function resolveApiConfig(Provider $provider, ?string $environment = null): ?ProviderApiConfig
    {
        $environment = strtolower($environment ?? (string) config('wallets.default_environment', ProviderWallet::ENVIRONMENT_PRODUCTION));

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

    private function resolveAgencyTenant(Provider $provider): ?string
    {
        $tenant = data_get($provider->metadata, 'agency_tenant');

        if (filled($tenant)) {
            return (string) $tenant;
        }

        $tenant = config('wallets.provider_balance.tenant');

        return filled($tenant) ? (string) $tenant : null;
    }

    private function configurationErrorMessage(bool $missingApiConfig, bool $missingTenant): string
    {
        if ($missingApiConfig && $missingTenant) {
            return 'Set PROVIDER_API_BASE_URL, PROVIDER_API_TOKEN, and PROVIDER_AGENCY_TENANT in .env.';
        }

        if ($missingApiConfig) {
            return 'Set PROVIDER_API_BASE_URL and PROVIDER_API_TOKEN in .env.';
        }

        return 'Set PROVIDER_AGENCY_TENANT in .env or provider agency_tenant metadata.';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function failureMessage(int $status, ?array $payload): string
    {
        if (is_array($payload) && filled($payload['message'] ?? null)) {
            return (string) $payload['message'];
        }

        if ($status === 401 || $status === 403) {
            return 'Provider authentication failed while fetching wallet balances.';
        }

        if ($status >= 500) {
            return 'Provider returned a server error while fetching wallet balances.';
        }

        return 'Unable to fetch provider wallet balances.';
    }
}
