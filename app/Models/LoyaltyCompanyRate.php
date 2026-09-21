<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tier_id',
    'service_type',
    'company_key',
    'company_name',
    'discount_percentage',
    'is_active',
    'sort_order',
    'metadata',
])]
class LoyaltyCompanyRate extends Model
{
    public const COMPANY_HOTEL = 'hotel';

    public const COMPANY_ESIM = 'esim';

    public const COMPANY_INSURANCE = 'insurance';

    /**
     * Fixed catalog companies (one each) for non-flight services.
     *
     * @return array<int, array{service_type: string, company_key: string, company_name: string, sort_order: int}>
     */
    public static function catalogCompanies(): array
    {
        return [
            [
                'service_type' => Order::SERVICE_TYPE_HOTEL,
                'company_key' => self::COMPANY_HOTEL,
                'company_name' => 'Hotels',
                'sort_order' => 1000,
            ],
            [
                'service_type' => Order::SERVICE_TYPE_ESIM,
                'company_key' => self::COMPANY_ESIM,
                'company_name' => 'eSIM',
                'sort_order' => 1001,
            ],
            [
                'service_type' => Order::SERVICE_TYPE_INSURANCE,
                'company_key' => self::COMPANY_INSURANCE,
                'company_name' => 'Insurance',
                'sort_order' => 1002,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }

    public static function normalizeCompanyKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $normalized = strtoupper(trim($key));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Resolve the company key used for loyalty rate lookup.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function resolveCompanyKey(string $serviceType, array $attributes = []): ?string
    {
        $serviceType = strtolower(trim($serviceType));

        return match ($serviceType) {
            Order::SERVICE_TYPE_FLIGHT => self::normalizeCompanyKey(
                is_string($attributes['airline_code'] ?? null)
                    ? $attributes['airline_code']
                    : (is_string($attributes['company_key'] ?? null) ? $attributes['company_key'] : null),
            ),
            Order::SERVICE_TYPE_HOTEL => self::COMPANY_HOTEL,
            Order::SERVICE_TYPE_ESIM => self::COMPANY_ESIM,
            Order::SERVICE_TYPE_INSURANCE => self::COMPANY_INSURANCE,
            default => null,
        };
    }
}
