<?php

namespace App\Modules\Admin\AI\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\AI\Services\AiSettingsService;
use App\Modules\Settings\Services\SystemSettingsService;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class AiSettingsAdminService
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
        private readonly AiSettingsService $aiSettingsService,
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminPayload(): array
    {
        return [
            'settings' => $this->aiSettingsService->all(),
            'integration' => $this->aiSettingsService->integrationStatus(),
            'models' => $this->aiSettingsService->availableModels(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, array $payload): SystemSetting
    {
        if (array_key_exists('enabled', $payload)
            && (bool) $payload['enabled'] !== $this->aiSettingsService->enabled()
            && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can toggle AI travel assistant.');
        }

        $settings = SystemSetting::query()->first();

        if ($settings === null) {
            $settings = new SystemSetting(SystemSetting::defaultAttributes());
            $settings->save();
        }

        $metadata = is_array($settings->metadata) ? $settings->metadata : [];
        $before = is_array($metadata['ai'] ?? null) ? $metadata['ai'] : [];

        $ai = array_merge($before, Arr::only($payload, [
            'enabled',
            'provider',
            'model',
            'base_url',
            'timeout',
            'max_output_tokens',
            'temperature',
            'max_offers_for_recommendation',
            'max_conversation_turns',
            'timezone',
            'default_currency',
            'prefer_rules_nlu',
        ]));

        $ai['updated_at'] = now()->toDateTimeString();
        $metadata['ai'] = $ai;

        $settings->metadata = $metadata;
        $settings->settings_version = max(1, (int) ($settings->settings_version ?? 1)) + 1;
        $settings->updated_by_user_id = $actor->id;
        $settings->save();

        $this->systemSettingsService->forgetCache();

        $this->rbacAuditLogger->log(
            'ai.settings.updated',
            'settings.manage',
            $actor,
            'system_settings',
            $settings->id,
            [
                'settings_version' => $settings->settings_version,
                'before' => $before,
                'after' => $ai,
            ],
        );

        return $settings->refresh();
    }
}
