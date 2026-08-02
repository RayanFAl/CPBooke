<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'company_legal_name',
    'company_display_name',
    'support_email',
    'support_phone',
    'website_url',
    'tax_id',
    'company_address',
    'default_currency',
    'timezone',
    'default_locale',
    'default_margin_percent',
    'email_enabled',
    'sms_enabled',
    'whatsapp_enabled',
    'push_enabled',
    'mail_from_name',
    'sms_sender_id',
    'maintenance_mode',
    'support_chat_enabled',
    'orders_legacy_create_enabled',
    'home_offers_enabled',
    'settings_version',
    'updated_by_user_id',
    'metadata',
])]
class SystemSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_margin_percent' => 'decimal:2',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'maintenance_mode' => 'boolean',
            'support_chat_enabled' => 'boolean',
            'orders_legacy_create_enabled' => 'boolean',
            'home_offers_enabled' => 'boolean',
            'settings_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('system_settings')) {
            return new self(self::defaultAttributes());
        }

        return self::query()->first() ?? self::query()->create(self::defaultAttributes());
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'company_legal_name' => config('app.name', 'CPBooke'),
            'company_display_name' => config('app.name', 'CPBooke'),
            'support_email' => config('mail.from.address'),
            'support_phone' => null,
            'website_url' => config('app.url'),
            'tax_id' => null,
            'company_address' => null,
            'default_currency' => (string) config('settlements.default_currency', 'LYD'),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'default_locale' => (string) config('app.locale', 'en'),
            'default_margin_percent' => null,
            'email_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'push_enabled' => true,
            'mail_from_name' => config('mail.from.name'),
            'sms_sender_id' => null,
            'maintenance_mode' => false,
            'support_chat_enabled' => true,
            'orders_legacy_create_enabled' => false,
            'home_offers_enabled' => true,
            'settings_version' => 1,
            'updated_by_user_id' => null,
            'metadata' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminPayload(): array
    {
        return [
            'company_legal_name' => $this->company_legal_name,
            'company_display_name' => $this->company_display_name,
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'website_url' => $this->website_url,
            'tax_id' => $this->tax_id,
            'company_address' => $this->company_address,
            'default_currency' => $this->default_currency,
            'timezone' => $this->timezone,
            'default_locale' => $this->default_locale,
            'default_margin_percent' => $this->default_margin_percent,
            'email_enabled' => (bool) $this->email_enabled,
            'sms_enabled' => (bool) $this->sms_enabled,
            'whatsapp_enabled' => (bool) $this->whatsapp_enabled,
            'push_enabled' => (bool) $this->push_enabled,
            'mail_from_name' => $this->mail_from_name,
            'sms_sender_id' => $this->sms_sender_id,
            'maintenance_mode' => (bool) $this->maintenance_mode,
            'support_chat_enabled' => (bool) $this->support_chat_enabled,
            'orders_legacy_create_enabled' => (bool) $this->orders_legacy_create_enabled,
            'home_offers_enabled' => (bool) $this->home_offers_enabled,
            'settings_version' => (int) $this->settings_version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
