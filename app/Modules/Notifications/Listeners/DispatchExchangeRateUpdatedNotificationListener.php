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
        $changes = $event->changes;
        $actorId = $event->actor?->id;

        if ($changes === []) {
            return;
        }

        dispatch(function () use ($changes, $actorId): void {
            $actor = $actorId
                ? \App\Models\User::query()->find($actorId)
                : null;

            app(NotificationService::class)->dispatchForEvent(
                new ExchangeRateUpdated(
                    changes: $changes,
                    actor: $actor,
                ),
            );
        })->afterResponse();
    }
}
