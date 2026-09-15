<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Admin\ExchangeRates\Events\ExchangeRateUpdated;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Exchange-rate alerts must not depend on a queue worker.
 * The admin response returns first; push + in-app run afterwards in-process.
 */
class DispatchExchangeRateUpdatedNotificationListener
{
    public function handle(ExchangeRateUpdated $event): void
    {
        $currencyCode = $event->currencyCode;
        $oldBuy = $event->oldBuy;
        $newBuy = $event->newBuy;
        $oldSell = $event->oldSell;
        $newSell = $event->newSell;
        $actorId = $event->actor?->id;

        dispatch(function () use ($currencyCode, $oldBuy, $newBuy, $oldSell, $newSell, $actorId): void {
            $actor = $actorId
                ? \App\Models\User::query()->find($actorId)
                : null;

            app(NotificationService::class)->dispatchForEvent(
                new ExchangeRateUpdated(
                    currencyCode: $currencyCode,
                    oldBuy: $oldBuy,
                    newBuy: $newBuy,
                    oldSell: $oldSell,
                    newSell: $newSell,
                    actor: $actor,
                ),
            );
        })->afterResponse();
    }
}
