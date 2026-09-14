<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\LoyaltyHistory;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Loyalty welcome / tier alerts must not depend on a queue worker.
 */
class DispatchLoyaltyTierChangedNotificationListener
{
    public function handle(LoyaltyTierChanged $event): void
    {
        $historyId = (int) $event->history->id;

        dispatch(function () use ($historyId): void {
            $history = LoyaltyHistory::query()
                ->with(['user', 'fromTier', 'toTier.benefits'])
                ->find($historyId);

            if ($history === null || $history->user === null) {
                return;
            }

            app(NotificationService::class)->dispatchForEvent(
                new LoyaltyTierChanged($history),
            );
        })->afterResponse();
    }
}
