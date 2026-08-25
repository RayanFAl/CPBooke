<?php

namespace App\Modules\Admin\Approvals\Http\Controllers;

use App\Models\Approval;
use App\Modules\Admin\Approvals\Http\Requests\RejectApprovalRequest;
use App\Modules\Approvals\Services\ApprovalService;
use App\Modules\Audit\Services\EntityTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly EntityTimelineService $entityTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('approvals.view');

        $status = trim((string) $request->input('status', 'pending'));
        $type = trim((string) $request->input('type', ''));

        $approvals = Approval::query()
            ->with([
                'requester:id,name,full_name',
                'approver:id,name,full_name',
                'rejector:id,name,full_name',
            ])
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Approval $approval): array => $this->serializeApproval($approval));

        return Inertia::render('admin/approvals/pages/Index', [
            'approvals' => $approvals,
            'filters' => [
                'status' => $status,
                'type' => $type,
            ],
            'can_approve' => $request->user()?->can('approvals.approve') ?? false,
            'types' => [
                Approval::TYPE_REFUND,
                Approval::TYPE_CANCEL,
                Approval::TYPE_WALLET_DEPOSIT,
                Approval::TYPE_WALLET_ADJUSTMENT,
                Approval::TYPE_SETTLEMENT_ADJUSTMENT,
            ],
            'statuses' => [
                Approval::STATUS_PENDING,
                Approval::STATUS_APPROVED,
                Approval::STATUS_REJECTED,
                Approval::STATUS_EXECUTED,
                Approval::STATUS_FAILED,
            ],
        ]);
    }

    public function show(Request $request, Approval $approval): Response
    {
        Gate::authorize('approvals.view');

        $approval->loadMissing([
            'requester:id,name,full_name',
            'approver:id,name,full_name',
            'rejector:id,name,full_name',
        ]);

        return Inertia::render('admin/approvals/pages/Show', [
            'approval' => $this->serializeApproval($approval),
            'system_timeline' => $this->entityTimelineService->forApproval($approval),
            'can_approve' => $request->user()?->can('approvals.approve') ?? false,
        ]);
    }

    public function approve(Request $request, Approval $approval): RedirectResponse
    {
        Gate::authorize('approvals.approve');

        $result = $this->approvalService->approve($approval, $request->user());

        if ($result->isFailed()) {
            return redirect()
                ->route('admin.approvals.index', ['status' => 'failed'])
                ->with('error', 'Approval #'.$result->id.' was approved but execution failed. You can retry.');
        }

        return redirect()
            ->route('admin.approvals.index', ['status' => 'all'])
            ->with('success', 'Approval #'.$result->id.' executed successfully.');
    }

    public function reject(RejectApprovalRequest $request, Approval $approval): RedirectResponse
    {
        $this->approvalService->reject(
            $approval,
            $request->user(),
            $request->string('rejection_reason')->value(),
        );

        return redirect()
            ->route('admin.approvals.index', ['status' => 'rejected'])
            ->with('success', 'Approval #'.$approval->id.' rejected.');
    }

    public function retry(Request $request, Approval $approval): RedirectResponse
    {
        Gate::authorize('approvals.approve');

        $result = $this->approvalService->retryExecution($approval, $request->user());

        if ($result->isFailed()) {
            return redirect()
                ->route('admin.approvals.index', ['status' => 'failed'])
                ->with('error', 'Retry failed for approval #'.$result->id.': '.($result->execution_error ?? 'unknown error'));
        }

        return redirect()
            ->route('admin.approvals.index', ['status' => 'all'])
            ->with('success', 'Approval #'.$result->id.' executed successfully after retry.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApproval(Approval $approval): array
    {
        $payload = $approval->payload ?? [];
        $snapshot = $payload['snapshot'] ?? [];

        return [
            'id' => $approval->id,
            'type' => $approval->type,
            'entity_type' => $approval->entity_type,
            'entity_id' => $approval->entity_id,
            'status' => $approval->status,
            'reason' => $approval->reason,
            'rejection_reason' => $approval->rejection_reason,
            'payload' => $payload,
            'snapshot' => $snapshot,
            'execution_result' => $approval->execution_result,
            'execution_error' => $approval->execution_error,
            'requested_by' => $approval->requester?->full_name ?: $approval->requester?->name,
            'approved_by' => $approval->approver?->full_name ?: $approval->approver?->name,
            'rejected_by' => $approval->rejector?->full_name ?: $approval->rejector?->name,
            'created_at' => optional($approval->created_at)?->toIso8601String(),
            'approved_at' => optional($approval->approved_at)?->toIso8601String(),
            'rejected_at' => optional($approval->rejected_at)?->toIso8601String(),
            'executed_at' => optional($approval->executed_at)?->toIso8601String(),
        ];
    }
}
