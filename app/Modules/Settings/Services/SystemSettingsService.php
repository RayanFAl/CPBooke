<?php

namespace App\Modules\Settings\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSettingsService
{
    public const CACHE_KEY = 'system_settings.current';

    public const CACHE_TTL_SECONDS = 60;

    public function current(): SystemSetting
    {
        if (! Schema::hasTable('system_settings')) {
            return SystemSetting::current();
        }

        // Cache attribute arrays only — never Eloquent models (file/redis
        // serialization can revive them as __PHP_Incomplete_Class).
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $this->hydrateFromAttributes($cached);
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $settings = SystemSetting::current();

        Cache::put(
            self::CACHE_KEY,
            [
                'attributes' => $settings->getAttributes(),
                'exists' => $settings->exists,
            ],
            self::CACHE_TTL_SECONDS,
        );

        return $settings;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array{attributes?: array<string, mixed>, exists?: bool}|array<string, mixed>  $cached
     */
    private function hydrateFromAttributes(array $cached): SystemSetting
    {
        $attributes = is_array($cached['attributes'] ?? null)
            ? $cached['attributes']
            : $cached;

        $settings = new SystemSetting;
        $settings->setRawAttributes($attributes, true);
        $settings->exists = (bool) ($cached['exists'] ?? isset($attributes['id']));
        $settings->syncOriginal();

        return $settings;
    }

    public function defaultCurrency(): string
    {
        $currency = strtoupper(trim((string) ($this->current()->default_currency ?: 'LYD')));

        return $currency !== '' ? $currency : 'LYD';
    }

    public function companyName(): string
    {
        $name = trim((string) ($this->current()->company_name ?: ''));

        return $name !== '' ? $name : (string) config('app.name', 'CPBooke');
    }

    public function mailFromName(): string
    {
        $from = trim((string) ($this->current()->email_from_name ?: ''));

        return $from !== '' ? $from : $this->companyName();
    }

    public function defaultCommissionPercent(): ?float
    {
        $value = $this->current()->default_commission_percent;

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    public function isChannelEnabled(string $channel): bool
    {
        return match ($channel) {
            'email' => (bool) $this->current()->channel_email_enabled,
            'sms' => (bool) $this->current()->channel_sms_enabled,
            'whatsapp' => (bool) $this->current()->channel_whatsapp_enabled,
            'push' => (bool) $this->current()->channel_push_enabled,
            'in_app' => true,
            default => true,
        };
    }

    public function feature(string $flag): bool
    {
        return match ($flag) {
            'maintenance' => (bool) $this->current()->feature_maintenance_mode,
            'chat' => (bool) $this->current()->feature_chat_enabled,
            'legacy_order_create' => (bool) $this->current()->feature_legacy_order_create,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function platformPayload(): array
    {
        $settings = $this->current();

        return [
            'company_name' => $this->companyName(),
            'company_address' => $settings->company_address,
            'support_email' => $settings->support_email,
            'support_phone' => $settings->support_phone,
            'tax_id' => $settings->tax_id,
            'default_currency' => $this->defaultCurrency(),
            'timezone' => $settings->timezone ?: config('app.timezone'),
            'locale' => $settings->locale ?: config('app.locale'),
            'feature_maintenance_mode' => (bool) $settings->feature_maintenance_mode,
            'feature_chat_enabled' => (bool) $settings->feature_chat_enabled,
            'settings_version' => (int) ($settings->settings_version ?? 1),
        ];
    }
}
