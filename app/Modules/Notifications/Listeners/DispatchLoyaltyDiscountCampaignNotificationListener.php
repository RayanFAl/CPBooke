<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Loyalty\Events\LoyaltyDiscountCampaignQueued;
use App\Modules\Notifications\Jobs\BroadcastLoyaltyDiscountCampaignJob;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;

/**
 * Campaign pushes must not block the admin create-tier response.
 */
class DispatchLoyaltyDiscountCampaignNotificationListener
{
    public function handle(LoyaltyDiscountCampaignQueued $event): void
    {
        $tierId = $event->tierId;
        $tierName = $event->tierName;
        $discountPercentage = $event->discountPercentage;
        $durationLabelEn = $event->durationLabelEn;
        $durationLabelAr = $event->durationLabelAr;

        dispatch(function () use (
            $tierId,
            $tierName,
            $discountPercentage,
            $durationLabelEn,
            $durationLabelAr,
        ): void {
            (new BroadcastLoyaltyDiscountCampaignJob(
                tierId: $tierId,
                tierName: $tierName,
                discountPercentage: $discountPercentage,
                durationLabelEn: $durationLabelEn,
                durationLabelAr: $durationLabelAr,
            ))->handle(
                app(NotificationService::class),
                app(NotificationTemplateSyncService::class),
            );
        })->afterResponse();
    }
}
