<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

#[Fillable([
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
            'default_commission_percent' => 'decimal:2',
            'channel_email_enabled' => 'boolean',
            'channel_sms_enabled' => 'boolean',
            'channel_whatsapp_enabled' => 'boolean',
            'channel_push_enabled' => 'boolean',
            'feature_maintenance_mode' => 'boolean',
            'feature_chat_enabled' => 'boolean',
            'feature_legacy_order_create' => 'boolean',
            'settings_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function current(): self
    {
        if (! Schema::hasTable('system_settings')) {
            return new self(self::defaultAttributes());
        }

        return self::query()->first() ?? new self(self::defaultAttributes());
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'company_name' => config('app.name', 'CPBooke'),
            'company_address' => null,
            'support_email' => config('mail.addresses.support'),
            'support_phone' => null,
            'tax_id' => null,
            'logo_path' => null,
            'default_currency' => strtoupper((string) config('settlements.default_currency', 'LYD')),
            'timezone' => 'Africa/Tripoli',
            'locale' => (string) config('app.locale', 'en'),
            'default_commission_percent' => null,
            'channel_email_enabled' => true,
            'channel_sms_enabled' => true,
            'channel_whatsapp_enabled' => true,
            'channel_push_enabled' => true,
            'email_from_name' => config('mail.from.name'),
            'sms_sender_name' => null,
            'whatsapp_sender_name' => null,
            'feature_maintenance_mode' => false,
            'feature_chat_enabled' => true,
            'feature_legacy_order_create' => false,
            'settings_version' => 1,
            'updated_by_user_id' => null,
            'metadata' => [],
        ];
    }
}
