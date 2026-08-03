<?php

namespace App\Jobs;

use App\Models\Approval;
use App\Modules\Approvals\Services\ApprovalActionExecutor;
use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Executes an already-approved approval action asynchronously.
 */
class ExecuteApprovalActionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $approvalId,
    ) {
    }

    public function handle(
        ApprovalActionExecutor $executor,
        ApplicationEventRecorder $recorder,
    ): void {
        $approval = Approval::query()->find($this->approvalId);

        if ($approval === null) {
            return;
        }

        if (! in_array($approval->status, [Approval::STATUS_APPROVED, Approval::STATUS_FAILED], true)) {
            return;
        }

        try {
            $result = $executor->execute($approval);

            $approval->forceFill([
                'status' => Approval::STATUS_EXECUTED,
                'execution_result' => $result,
                'execution_error' => null,
                'executed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $approval->forceFill([
                'status' => Approval::STATUS_FAILED,
                'execution_error' => $exception->getMessage(),
                'executed_at' => now(),
            ])->save();

            $recorder->exception($exception, 'approval_job', [
                'approval_id' => $approval->id,
                'type' => $approval->type,
            ]);

            throw $exception;
        }
    }
}
