<?php

namespace App\Modules\Approvals\Services;

use App\Jobs\ExecuteApprovalActionJob;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Audit\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApprovalService
{
    public function __construct(
        private readonly ApprovalRuleService $rules,
        private readonly ApprovalActionExecutor $executor,
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    /**
     * Submit an action for approval or execute immediately when allowed.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ruleContext
     * @return array{executed: bool, approval: Approval|null, result: array<string, mixed>|null}
     */
    public function submit(
        string $type,
        string $entityType,
        int $entityId,
        User $requester,
        array $payload,
        string $reason,
        array $ruleContext = [],
    ): array {
        if ($this->rules->requiresApproval($type, $requester, $ruleContext)) {
            $approval = $this->createPending($type, $entityType, $entityId, $requester, $payload, $reason);

            $this->auditRecorder->success(
                AuditLog::MODULE_APPROVALS,
                'approval.requested',
                'Approval requested for '.$type,
                AuditLog::ENTITY_APPROVAL,
                $approval->id,
                $requester,
                null,
                ['type' => $type, 'status' => $approval->status],
                ['entity_type' => $entityType, 'entity_id' => $entityId, 'reason' => $reason],
            );

            return [
                'executed' => false,
                'approval' => $approval,
                'result' => null,
            ];
        }

        $approval = $this->createAutoApproved($type, $entityType, $entityId, $requester, $payload, $reason);
        $result = $this->executeApproved($approval);

        $this->auditRecorder->success(
            AuditLog::MODULE_APPROVALS,
            'approval.auto_approved',
            'Approval auto-approved and executed for '.$type,
            AuditLog::ENTITY_APPROVAL,
            $approval->id,
            $requester,
            null,
            ['type' => $type, 'status' => $approval->status],
            ['entity_type' => $entityType, 'entity_id' => $entityId],
        );

        return [
            'executed' => true,
            'approval' => $approval->refresh(),
            'result' => $result,
        ];
    }

    public function approve(Approval $approval, User $approver): Approval
    {
        if (! $this->rules->canApprove($approver)) {
            throw new AuthorizationException('You are not authorized to approve this request.');
        }

        if (! $approval->isPending()) {
            throw ValidationException::withMessages([
                'approval' => 'Only pending approvals can be approved.',
            ]);
        }

        if ((int) $approval->requested_by === (int) $approver->id && ! $approver->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'approval' => 'You cannot approve your own request.',
            ]);
        }

        // Persist approval before execution so a failed API call does not roll back the decision.
        $approval->forceFill([
            'status' => Approval::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_APPROVALS,
            'approval.approved',
            'Approval #'.$approval->id.' approved',
            AuditLog::ENTITY_APPROVAL,
            $approval->id,
            $approver,
            ['status' => Approval::STATUS_PENDING],
            ['status' => Approval::STATUS_APPROVED],
        );

        try {
            $this->executeApproved($approval);
        } catch (Throwable) {
            // Status is already STATUS_FAILED with execution_error; caller can retry.
        }

        return $approval->refresh();
    }

    public function reject(Approval $approval, User $rejector, string $reason): Approval
    {
        if (! $this->rules->canApprove($rejector)) {
            throw new AuthorizationException('You are not authorized to reject this request.');
        }

        if (! $approval->isPending()) {
            throw ValidationException::withMessages([
                'approval' => 'Only pending approvals can be rejected.',
            ]);
        }

        $approval->forceFill([
            'status' => Approval::STATUS_REJECTED,
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_APPROVALS,
            'approval.rejected',
            'Approval #'.$approval->id.' rejected',
            AuditLog::ENTITY_APPROVAL,
            $approval->id,
            $rejector,
            ['status' => Approval::STATUS_PENDING],
            ['status' => Approval::STATUS_REJECTED, 'rejection_reason' => trim($reason)],
        );

        return $approval->refresh();
    }

    /**
     * Retry a previously approved action whose execution failed (timeout, API down, etc.).
     */
    public function retryExecution(Approval $approval, User $actor): Approval
    {
        if (! $this->rules->canApprove($actor)) {
            throw new AuthorizationException('You are not authorized to retry this execution.');
        }

        if (! $approval->isFailed()) {
            throw ValidationException::withMessages([
                'approval' => 'Only failed approvals can be retried.',
            ]);
        }

        $approval->forceFill([
            'status' => Approval::STATUS_APPROVED,
            'execution_error' => null,
            'executed_at' => null,
        ])->save();

        try {
            $this->executeApproved($approval);
        } catch (Throwable) {
            // Remains STATUS_FAILED with a fresh execution_error.
        }

        return $approval->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createPending(
        string $type,
        string $entityType,
        int $entityId,
        User $requester,
        array $payload,
        string $reason,
    ): Approval {
        return Approval::query()->create([
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => Approval::STATUS_PENDING,
            'requested_by' => $requester->id,
            'reason' => trim($reason),
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createAutoApproved(
        string $type,
        string $entityType,
        int $entityId,
        User $requester,
        array $payload,
        string $reason,
    ): Approval {
        return Approval::query()->create([
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => Approval::STATUS_APPROVED,
            'requested_by' => $requester->id,
            'approved_by' => $requester->id,
            'approved_at' => now(),
            'reason' => trim($reason),
            'payload' => array_merge($payload, ['auto_approved' => true]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function executeApproved(Approval $approval): array
    {
        if (config('monitoring.execute_approvals_async')) {
            ExecuteApprovalActionJob::dispatch($approval->id);

            return [
                'queued' => true,
                'approval_id' => $approval->id,
            ];
        }

        try {
            $result = $this->executor->execute($approval);

            $approval->forceFill([
                'status' => Approval::STATUS_EXECUTED,
                'execution_result' => $result,
                'execution_error' => null,
                'executed_at' => now(),
            ])->save();

            $this->auditRecorder->success(
                AuditLog::MODULE_APPROVALS,
                'approval.executed',
                'Approval #'.$approval->id.' executed',
                AuditLog::ENTITY_APPROVAL,
                $approval->id,
                null,
                ['status' => Approval::STATUS_APPROVED],
                ['status' => Approval::STATUS_EXECUTED],
                ['entity_type' => $approval->entity_type, 'entity_id' => $approval->entity_id],
            );

            return $result;
        } catch (Throwable $exception) {
            $approval->forceFill([
                'status' => Approval::STATUS_FAILED,
                'execution_error' => $exception->getMessage(),
                'executed_at' => now(),
            ])->save();

            $this->auditRecorder->failed(
                AuditLog::MODULE_APPROVALS,
                'approval.execution_failed',
                'Approval #'.$approval->id.' execution failed',
                AuditLog::ENTITY_APPROVAL,
                $approval->id,
                null,
                ['status' => Approval::STATUS_APPROVED],
                ['status' => Approval::STATUS_FAILED, 'error' => $exception->getMessage()],
                ['entity_type' => $approval->entity_type, 'entity_id' => $approval->entity_id],
            );

            throw $exception;
        }
    }
}
