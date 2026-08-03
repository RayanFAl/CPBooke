<?php

namespace App\Jobs;

use App\Models\Settlement;
use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemindOpenSettlementsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ApplicationEventRecorder $recorder): void
    {
        $open = Settlement::query()
            ->with('provider:id,name,key')
            ->whereIn('status', [Settlement::STATUS_DRAFT, Settlement::STATUS_OPEN])
            ->whereDate('period_end', '<', now()->startOfMonth()->toDateString())
            ->get();

        foreach ($open as $settlement) {
            $recorder->record(
                'system',
                'warning',
                'Settlement reminder: #'.$settlement->id.' ('.($settlement->provider?->name ?? 'provider').') still '.$settlement->status,
                'settlement_reminder',
                [
                    'settlement_id' => $settlement->id,
                    'provider_id' => $settlement->provider_id,
                    'status' => $settlement->status,
                ],
            );
        }
    }
}
