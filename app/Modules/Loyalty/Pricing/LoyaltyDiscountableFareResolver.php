<?php

namespace App\Modules\Loyalty\Pricing;

final class LoyaltyDiscountableFareResolver
{
    /**
     * Resolve the fare amount that loyalty discounts may apply to.
     */
    public static function resolve(float $total, ?float $tax = null, ?float $baseFare = null): float
    {
        if ($baseFare !== null && $baseFare > 0) {
            return round($baseFare, 2);
        }

        if ($tax !== null && $tax > 0) {
            return round(max($total - $tax, 0), 2);
        }

        return round(max($total, 0), 2);
    }

    /**
     * Build the customer-facing total after a fare-only discount.
     */
    public static function finalTotal(float $fare, float $discount, ?float $tax = null): float
    {
        return round(max($fare - $discount, 0) + max($tax ?? 0, 0), 2);
    }
}
