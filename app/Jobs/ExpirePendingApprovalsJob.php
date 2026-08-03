<?php

namespace App\Jobs;

use App\Models\Approval;
use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePendingApprovalsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ApplicationEventRecorder $recorder): void
    {
        $hours = (int) config('monitoring.approvals.expire_pending_after_hours', 72);
        $cutoff = now()->subHours($hours);

        $expired = Approval::query()
            ->where('status', Approval::STATUS_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($expired as $approval) {
            $approval->forceFill([
                'status' => Approval::STATUS_REJECTED,
                'rejection_reason' => "Auto-expired after {$hours} hours without approval.",
                'rejected_at' => now(),
            ])->save();
        }

        if ($expired->isNotEmpty()) {
            $recorder->record(
                'system',
                'warning',
                'Expired '.$expired->count().' pending approval(s)',
                'scheduler',
                ['count' => $expired->count(), 'hours' => $hours],
            );
        }
    }
}
