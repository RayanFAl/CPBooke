<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'loyalty_enabled',
    'auto_upgrade_enabled',
    'auto_downgrade_enabled',
    'visible_in_mobile_app',
    'allow_discount_stacking',
    'max_global_discount_amount',
    'minimum_discountable_order_amount',
    'default_currency',
    'settings_version',
    'updated_by_user_id',
    'metadata',
])]
class LoyaltySetting extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loyalty_enabled' => 'boolean',
            'auto_upgrade_enabled' => 'boolean',
            'auto_downgrade_enabled' => 'boolean',
            'visible_in_mobile_app' => 'boolean',
            'allow_discount_stacking' => 'boolean',
            'max_global_discount_amount' => 'decimal:2',
            'minimum_discountable_order_amount' => 'decimal:2',
            'settings_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('loyalty_settings')) {
            return new self(self::defaultAttributes());
        }

        return self::query()->first() ?? new self(self::defaultAttributes());
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultAttributes(): array
    {
        return [
            'loyalty_enabled' => true,
            'auto_upgrade_enabled' => true,
            'auto_downgrade_enabled' => false,
            'visible_in_mobile_app' => true,
            'allow_discount_stacking' => false,
            'max_global_discount_amount' => null,
            'minimum_discountable_order_amount' => null,
            'default_currency' => 'LYD',
            'settings_version' => 1,
            'updated_by_user_id' => null,
            'metadata' => [],
        ];
    }
}