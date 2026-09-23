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

        if ($name === '' || in_array($name, ['BookNow', 'Booke', 'بوكي', 'CPBooke', 'Laravel'], true)) {
            return 'Booke';
        }

        return $name;
    }

    public function mailFromName(): string
    {
        $from = trim((string) ($this->current()->email_from_name ?: ''));

        return $from !== '' ? $from : $this->companyName();
    }

    public function supportEmail(): string
    {
        return $this->resolvedEmail(
            $this->current()->support_email,
            (string) config('mail.addresses.support'),
        );
    }

    public function noreplyEmail(): string
    {
        return $this->resolvedEmail(
            $this->current()->noreply_email,
            (string) config('mail.addresses.noreply', config('mail.from.address')),
        );
    }

    public function infoEmail(): string
    {
        return $this->resolvedEmail(
            $this->current()->info_email,
            (string) config('mail.addresses.info'),
        );
    }

    public function feedbackEmail(): string
    {
        return $this->resolvedEmail(
            $this->current()->feedback_email,
            (string) config('mail.addresses.feedback'),
        );
    }

    public function supportPhone(): ?string
    {
        $phone = trim((string) ($this->current()->support_phone ?: ''));

        return $phone !== '' ? $phone : null;
    }

    public function smsEndpoint(): ?string
    {
        return $this->resolvedCredential(
            $this->current()->sms_endpoint ?? null,
            (string) config('services.notifications.sms_endpoint', ''),
        );
    }

    public function smsToken(): ?string
    {
        return $this->resolvedCredential(
            $this->current()->sms_token ?? null,
            (string) config('services.notifications.sms_token', ''),
        );
    }

    public function whatsappEndpoint(): ?string
    {
        return $this->resolvedCredential(
            $this->current()->whatsapp_endpoint ?? null,
            (string) config('services.notifications.whatsapp_endpoint', ''),
        );
    }

    public function whatsappToken(): ?string
    {
        return $this->resolvedCredential(
            $this->current()->whatsapp_token ?? null,
            (string) config('services.notifications.whatsapp_token', ''),
        );
    }

    public function isSmsGatewayConfigured(): bool
    {
        return $this->smsEndpoint() !== null;
    }

    public function isWhatsAppGatewayConfigured(): bool
    {
        return $this->whatsappEndpoint() !== null;
    }

    private function resolvedCredential(mixed $value, string $fallback): ?string
    {
        $resolved = trim((string) ($value ?: ''));
        if ($resolved === '') {
            $resolved = trim($fallback);
        }

        return $resolved !== '' ? $resolved : null;
    }

    private function resolvedEmail(mixed $value, string $fallback): string
    {
        $email = trim((string) ($value ?: ''));

        return $email !== '' ? $email : trim($fallback);
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
            'support_email' => $this->supportEmail(),
            'noreply_email' => $this->noreplyEmail(),
            'info_email' => $this->infoEmail(),
            'feedback_email' => $this->feedbackEmail(),
            'support_phone' => $this->supportPhone(),
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
