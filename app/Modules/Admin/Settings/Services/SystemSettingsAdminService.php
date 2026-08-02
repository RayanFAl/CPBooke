<?php

namespace App\Modules\Admin\Settings\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Rbac\RbacAuditLogger;
use Illuminate\Support\Facades\Cache;

class SystemSettingsAdminService
{
    public const CACHE_KEY = 'system_settings.current';

    public function __construct(
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {}

    public function getSettings(): SystemSetting
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function (): SystemSetting {
            return SystemSetting::current();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $admin, array $data): SystemSetting
    {
        $settings = SystemSetting::current();
        $settings->forceFill($data);
        $settings->settings_version = max(1, (int) ($settings->settings_version ?? 1)) + 1;
        $settings->updated_by_user_id = $admin->id;
        $settings->save();

        Cache::forget(self::CACHE_KEY);

        $this->rbacAuditLogger->log(
            'settings.updated',
            'settings.manage',
            $admin,
            'system_settings',
            $settings->id,
            [
                'settings_version' => $settings->settings_version,
                'default_currency' => $settings->default_currency,
                'maintenance_mode' => (bool) $settings->maintenance_mode,
            ],
        );

        return $settings->refresh();
    }
}
