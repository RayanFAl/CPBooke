<?php

namespace App\Modules\Admin\Settings\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Settings\Services\SystemSettingsService;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class SystemSettingsAdminService
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {
    }

    public function getSettings(): SystemSetting
    {
        return SystemSetting::current();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, array $payload): SystemSetting
    {
        $settings = SystemSetting::current();
        $before = $settings->only(array_keys(SystemSetting::defaultAttributes()));

        if (array_key_exists('feature_maintenance_mode', $payload)
            && (bool) $payload['feature_maintenance_mode'] !== (bool) $settings->feature_maintenance_mode
            && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can toggle maintenance mode.');
        }

        if (array_key_exists('feature_legacy_order_create', $payload)
            && (bool) $payload['feature_legacy_order_create'] !== (bool) $settings->feature_legacy_order_create
            && ! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            throw new AuthorizationException('Only super admins can toggle legacy order create.');
        }

        $fillable = Arr::only($payload, [
            'company_name',
            'company_address',
            'support_email',
            'support_phone',
            'tax_id',
            'logo_path',
            'default_currency',
            'timezone',
            'locale',
            'default_commission_percent',
            'channel_email_enabled',
            'channel_sms_enabled',
            'channel_whatsapp_enabled',
            'channel_push_enabled',
            'email_from_name',
            'sms_sender_name',
            'whatsapp_sender_name',
            'feature_maintenance_mode',
            'feature_chat_enabled',
            'feature_legacy_order_create',
        ]);

        $settings->forceFill($fillable);
        $settings->settings_version = max(1, (int) ($settings->settings_version ?? 1)) + 1;
        $settings->updated_by_user_id = $actor->id;
        $settings->save();

        $this->systemSettingsService->forgetCache();

        $this->rbacAuditLogger->log(
            'system.settings.updated',
            'settings.manage',
            $actor,
            'system_settings',
            $settings->id,
            [
                'settings_version' => $settings->settings_version,
                'changed' => array_keys($fillable),
                'before' => Arr::only($before, array_keys($fillable)),
                'after' => Arr::only($settings->toArray(), array_keys($fillable)),
            ],
        );

        return $settings->refresh();
    }
}
