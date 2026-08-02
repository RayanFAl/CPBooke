<?php

namespace App\Support\Platform;

use App\Models\SystemSetting;
use App\Modules\Admin\Settings\Services\SystemSettingsAdminService;
use Throwable;

class PlatformSettings
{
    public static function current(): SystemSetting
    {
        try {
            return app(SystemSettingsAdminService::class)->getSettings();
        } catch (Throwable) {
            return SystemSetting::current();
        }
    }

    public static function defaultCurrency(string $fallback = 'LYD'): string
    {
        try {
            $currency = self::current()->default_currency;
        } catch (Throwable) {
            return $fallback;
        }

        $currency = is_string($currency) ? strtoupper(trim($currency)) : '';

        return $currency !== '' ? $currency : $fallback;
    }

    public static function supportChatEnabled(): bool
    {
        try {
            return (bool) self::current()->support_chat_enabled;
        } catch (Throwable) {
            return true;
        }
    }

    public static function ordersLegacyCreateEnabled(): bool
    {
        try {
            return (bool) self::current()->orders_legacy_create_enabled;
        } catch (Throwable) {
            return false;
        }
    }

    public static function homeOffersEnabled(): bool
    {
        try {
            return (bool) self::current()->home_offers_enabled;
        } catch (Throwable) {
            return true;
        }
    }

    public static function maintenanceMode(): bool
    {
        try {
            return (bool) self::current()->maintenance_mode;
        } catch (Throwable) {
            return false;
        }
    }
}
