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
    private const SECRET_FIELDS = ['sms_token', 'whatsapp_token'];

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
        if (! $settings->exists) {
            $settings->forceFill(SystemSetting::defaultAttributes())->save();
            $settings->refresh();
        }

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
            'noreply_email',
            'info_email',
            'feedback_email',
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
            'sms_endpoint',
            'whatsapp_sender_name',
            'whatsapp_endpoint',
            'feature_maintenance_mode',
            'feature_chat_enabled',
            'feature_legacy_order_create',
        ]);

        foreach ([
            'support_email',
            'support_phone',
            'noreply_email',
            'info_email',
            'feedback_email',
            'logo_path',
            'sms_sender_name',
            'sms_endpoint',
            'whatsapp_sender_name',
            'whatsapp_endpoint',
        ] as $nullableField) {
            if (array_key_exists($nullableField, $fillable) && $fillable[$nullableField] === '') {
                $fillable[$nullableField] = null;
            }
        }

        foreach (['sms_token', 'whatsapp_token'] as $tokenField) {
            $clearFlag = 'clear_'.$tokenField;
            if (! empty($payload[$clearFlag])) {
                $fillable[$tokenField] = null;
                continue;
            }

            if (! array_key_exists($tokenField, $payload)) {
                continue;
            }

            $token = is_string($payload[$tokenField]) ? trim($payload[$tokenField]) : '';
            if ($token === '') {
                // Empty input keeps the existing secret.
                continue;
            }

            $fillable[$tokenField] = $token;
        }

        $settings->forceFill($fillable);
        $settings->settings_version = max(1, (int) ($settings->settings_version ?? 1)) + 1;
        $settings->updated_by_user_id = $actor->id;
        $settings->save();

        $this->systemSettingsService->forgetCache();

        $changedKeys = array_keys($fillable);
        $this->rbacAuditLogger->log(
            'system.settings.updated',
            'settings.manage',
            $actor,
            'system_settings',
            $settings->id,
            [
                'settings_version' => $settings->settings_version,
                'changed' => $changedKeys,
                'before' => $this->redactSecrets(Arr::only($before, $changedKeys)),
                'after' => $this->redactSecrets(Arr::only($settings->toArray(), $changedKeys)),
            ],
        );

        return $settings->refresh();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redactSecrets(array $values): array
    {
        foreach (self::SECRET_FIELDS as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $values[$field] = filled($values[$field]) ? '[set]' : null;
        }

        return $values;
    }
}
