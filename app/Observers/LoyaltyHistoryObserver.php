<?php

namespace App\Observers;

use App\Models\LoyaltyHistory;
use App\Modules\Admin\Governance\Events\LoyaltyStateChanged;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;

class LoyaltyHistoryObserver
{
    public function __construct(
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {
    }

    public function created(LoyaltyHistory $loyaltyHistory): void
    {
        $history = $loyaltyHistory->fresh()->load([
            'user',
            'fromTier',
            'toTier.benefits',
            'order',
        ]);

        event(new LoyaltyTierChanged($history));

        $this->governanceEventDispatcher->dispatch(new LoyaltyStateChanged(
            historyId: $history->id,
            userId: $history->user_id,
            fromTierId: $history->from_tier_id,
            fromTierName: $history->fromTier?->name,
            toTierId: $history->to_tier_id,
            toTierName: $history->toTier?->name,
            action: $history->action,
            orderId: $history->order_id,
            triggerEventClass: $history->trigger_event_class,
            metricsSnapshot: $history->metrics_snapshot ?? [],
            ruleSnapshot: $history->rule_snapshot,
            benefitCodes: $history->toTier?->benefits->pluck('code')->filter()->values()->all() ?? [],
            changedAt: $history->changed_at?->toIso8601String() ?? now()->toIso8601String(),
            occurredAt: now()->toIso8601String(),
        ));
    }
}