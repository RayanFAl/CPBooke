<?php

namespace App\Modules\Loyalty\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoyaltyDiscountCampaignAnnounced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<User>  $users
     */
    public function __construct(
        public readonly int $tierId,
        public readonly string $tierName,
        public readonly string $discountPercentage,
        public readonly string $durationLabelEn,
        public readonly string $durationLabelAr,
        public readonly array $users,
    ) {}
}
