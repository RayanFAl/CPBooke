<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id',
    'environment',
    'base_url',
    'auth_type',
    'api_key',
    'api_secret',
    'access_token',
    'refresh_token',
    'webhook_url',
    'timeout',
    'custom_headers',
    'status',
    'last_tested_at',
    'last_test_status',
    'last_test_http_status',
    'last_test_message',
    'last_test_latency_ms',
])]
class ProviderApiConfig extends Model
{
    public const ENVIRONMENT_SANDBOX = 'sandbox';

    public const ENVIRONMENT_PRODUCTION = 'production';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const AUTH_BEARER_TOKEN = 'bearer_token';

    public const AUTH_API_KEY = 'api_key';

    public const AUTH_API_KEY_SECRET = 'api_key_secret';

    public const AUTH_OAUTH2 = 'oauth2';

    public const AUTH_CUSTOM = 'custom';

    public const TEST_STATUS_SUCCESS = 'success';

    public const TEST_STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'custom_headers' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENVIRONMENT_PRODUCTION;
    }

    /**
     * @return array<int, string>
     */
    public static function authTypes(): array
    {
        return config('provider_api.auth_types', [
            self::AUTH_BEARER_TOKEN,
            self::AUTH_API_KEY,
            self::AUTH_API_KEY_SECRET,
            self::AUTH_OAUTH2,
            self::AUTH_CUSTOM,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function environments(): array
    {
        return config('provider_api.environments', [
            self::ENVIRONMENT_SANDBOX,
            self::ENVIRONMENT_PRODUCTION,
        ]);
    }
}
