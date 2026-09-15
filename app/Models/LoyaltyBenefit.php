<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tier_id',
    'code',
    'name',
    'description',
    'benefit_type',
    'value_type',
    'value',
    'configuration',
    'applies_to_services',
    'minimum_order_amount',
    'maximum_discount_amount',
    'priority',
    'stackable',
    'effective_from',
    'effective_to',
    'finance_sensitive',
    'created_by_user_id',
    'updated_by_user_id',
    'display_order',
    'is_highlighted',
    'is_active',
    'metadata',
])]
class LoyaltyBenefit extends Model
{
    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_SUPPORT = 'support';

    public const TYPE_OFFER = 'offer';

    public const TYPE_UPGRADE = 'upgrade';

    public const TYPE_SERVICE = 'service';

    public const VALUE_TYPE_PERCENTAGE = 'percentage';

    public const VALUE_TYPE_FIXED = 'fixed';

    public const VALUE_TYPE_FLAG = 'flag';

    public const VALUE_TYPE_TEXT = 'text';

    /**
     * Default catalog services eligible for loyalty percentage discounts.
     *
     * @return array<int, string>
     */
    public static function defaultDiscountServiceTypes(): array
    {
        return Order::serviceTypes();
    }

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'configuration' => 'array',
            'applies_to_services' => 'array',
            'minimum_order_amount' => 'decimal:2',
            'maximum_discount_amount' => 'decimal:2',
            'priority' => 'integer',
            'stackable' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'finance_sensitive' => 'boolean',
            'display_order' => 'integer',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }

    /**
     * Resolve which service types this benefit applies to.
     *
     * Prefer `applies_to_services`, then `configuration.applies_to`.
     * An empty list means the benefit applies to the full catalog
     * (flight, hotel, insurance, esim).
     *
     * @return array<int, string>
     */
    public function applicableServiceTypes(): array
    {
        $fromColumn = $this->normalizeServiceTypes($this->applies_to_services);

        if ($fromColumn !== []) {
            return $fromColumn;
        }

        $fromConfiguration = $this->normalizeServiceTypes(
            is_array($this->configuration) ? ($this->configuration['applies_to'] ?? null) : null,
        );

        if ($fromConfiguration !== []) {
            return $fromConfiguration;
        }

        return self::defaultDiscountServiceTypes();
    }

    public function appliesToServiceType(string $serviceType): bool
    {
        return in_array($serviceType, $this->applicableServiceTypes(), true);
    }

    /**
     * @param  mixed  $services
     * @return array<int, string>
     */
    private function normalizeServiceTypes(mixed $services): array
    {
        if (! is_array($services)) {
            return [];
        }

        $allowed = self::defaultDiscountServiceTypes();

        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $service): string => strtolower(trim((string) $service)),
                $services,
            ),
            static fn (string $service): bool => $service !== '' && in_array($service, $allowed, true),
        )));
    }
}