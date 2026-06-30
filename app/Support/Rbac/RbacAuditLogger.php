<?php

namespace App\Support\Rbac;

use App\Models\RbacAuditLog;
use App\Models\User;
use App\Modules\Admin\Governance\Events\RbacAuditRecorded;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use Illuminate\Support\Facades\Schema;

class RbacAuditLogger
{
    public function __construct(
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $action, ?string $permission = null, ?User $actor = null, ?string $targetType = null, int|string|null $targetId = null, array $context = []): void
    {
        if (! Schema::hasTable('rbac_audit_logs')) {
            return;
        }

        $request = request();

        $auditLog = RbacAuditLog::query()->create([
            'user_id' => $actor?->id,
            'permission' => $permission,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => is_numeric($targetId) ? (int) $targetId : null,
            'context' => $context,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        $this->governanceEventDispatcher->dispatch(new RbacAuditRecorded(
            auditLogId: $auditLog->id,
            actorId: $auditLog->user_id,
            permission: $auditLog->permission,
            action: $auditLog->action,
            targetType: $auditLog->target_type,
            targetId: $auditLog->target_id,
            context: $auditLog->context ?? [],
            ipAddress: $auditLog->ip_address,
            userAgent: $auditLog->user_agent,
            occurredAt: $auditLog->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ));
    }
}