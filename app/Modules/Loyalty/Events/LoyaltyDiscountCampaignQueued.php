<?php

namespace App\Modules\Loyalty\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when an admin announces a loyalty discount campaign to all customers.
 * The listener fans out per-user pushes after the HTTP response.
 */
class LoyaltyDiscountCampaignQueued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $tierId,
        public readonly string $tierName,
        public readonly string $discountPercentage,
        public readonly string $durationLabelEn,
        public readonly string $durationLabelAr,
    ) {}
}
