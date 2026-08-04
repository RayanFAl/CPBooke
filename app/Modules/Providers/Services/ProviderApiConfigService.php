<?php

namespace App\Modules\Providers\Services;

use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Models\ProviderService;
use App\Models\User;
use App\Modules\Audit\Services\AuditRecorder;
use Illuminate\Validation\ValidationException;

class ProviderApiConfigService
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ProviderApiConfigPresenter $presenter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Provider $provider, array $data, User $actor): ProviderApiConfig
    {
        $environment = strtolower((string) $data['environment']);
        $existing = ProviderApiConfig::query()
            ->where('provider_id', $provider->id)
            ->where('environment', $environment)
            ->first();

        if (
            $environment === ProviderApiConfig::ENVIRONMENT_PRODUCTION
            && ($existing === null || $existing->environment !== ProviderApiConfig::ENVIRONMENT_PRODUCTION)
            && ! ($data['confirm_production'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'confirm_production' => 'Production environment requires explicit confirmation.',
            ]);
        }

        $payload = [
            'base_url' => $data['base_url'] ?? null,
            'auth_type' => $data['auth_type'] ?? ProviderApiConfig::AUTH_API_KEY,
            'webhook_url' => $data['webhook_url'] ?? null,
            'timeout' => (int) ($data['timeout'] ?? config('provider_api.default_timeout', 30)),
            'custom_headers' => $data['custom_headers'] ?? null,
            'status' => $data['status'] ?? ProviderApiConfig::STATUS_ACTIVE,
        ];

        $this->applySecretFields($payload, $data, $existing);

        if ($existing) {
            $existing->forceFill($payload)->save();
            $config = $existing->refresh();
            $action = 'provider_api_config.updated';
        } else {
            $config = ProviderApiConfig::query()->create(array_merge($payload, [
                'provider_id' => $provider->id,
                'environment' => $environment,
            ]));
            $action = 'provider_api_config.created';
        }

        $this->auditRecorder->success(
            AuditLog::MODULE_PROVIDERS,
            $action,
            'Provider API config for '.$provider->name.' ('.$environment.')',
            AuditLog::ENTITY_PROVIDER,
            $provider->id,
            $actor,
            null,
            $this->presenter->serializeConfig($config),
            ['environment' => $environment],
        );

        return $config;
    }

    public function disable(Provider $provider, string $environment, User $actor): ProviderApiConfig
    {
        $config = ProviderApiConfig::query()
            ->where('provider_id', $provider->id)
            ->where('environment', strtolower($environment))
            ->firstOrFail();

        $config->forceFill(['status' => ProviderApiConfig::STATUS_DISABLED])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_PROVIDERS,
            'provider_api_config.disabled',
            'Provider API config disabled for '.$provider->name.' ('.$config->environment.')',
            AuditLog::ENTITY_PROVIDER,
            $provider->id,
            $actor,
            ['status' => ProviderApiConfig::STATUS_ACTIVE],
            ['status' => ProviderApiConfig::STATUS_DISABLED],
            ['environment' => $config->environment],
        );

        return $config->refresh();
    }

    public function logCredentialAccess(Provider $provider, User $actor, string $environment): void
    {
        $this->auditRecorder->success(
            AuditLog::MODULE_PROVIDERS,
            'provider_credentials.viewed',
            'Viewed provider credentials for '.$provider->name.' ('.$environment.')',
            AuditLog::ENTITY_PROVIDER,
            $provider->id,
            $actor,
            null,
            null,
            ['environment' => $environment],
        );
    }

    /**
     * @param  array<int, array{service: string, enabled?: bool, configuration?: array<string, mixed>|null}>  $services
   * @return array<int, ProviderService>
     */
    public function syncServices(Provider $provider, array $services, User $actor): array
    {
        $allowed = ProviderService::serviceKeys();
        $synced = [];

        foreach ($services as $serviceData) {
            $service = (string) ($serviceData['service'] ?? '');

            if (! in_array($service, $allowed, true)) {
                throw ValidationException::withMessages([
                    'services' => 'Unsupported provider service: '.$service,
                ]);
            }

            $record = ProviderService::query()->updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'service' => $service,
                ],
                [
                    'enabled' => (bool) ($serviceData['enabled'] ?? false),
                    'configuration' => $serviceData['configuration'] ?? null,
                ],
            );

            $synced[] = $record;
        }

        $this->auditRecorder->success(
            AuditLog::MODULE_PROVIDERS,
            'provider_services.synced',
            'Provider services updated for '.$provider->name,
            AuditLog::ENTITY_PROVIDER,
            $provider->id,
            $actor,
            null,
            ['services' => collect($synced)->map(fn (ProviderService $item): array => [
                'service' => $item->service,
                'enabled' => $item->enabled,
            ])->values()->all()],
        );

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function applySecretFields(array &$payload, array $data, ?ProviderApiConfig $existing): void
    {
        foreach (['api_key', 'api_secret', 'access_token', 'refresh_token'] as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) {
                $payload[$field] = $data[$field];
            }
        }

        if ($existing === null) {
            return;
        }

        foreach (['api_key', 'api_secret', 'access_token', 'refresh_token'] as $field) {
            if (! array_key_exists($field, $payload)) {
                $payload[$field] = $existing->{$field};
            }
        }
    }
}
