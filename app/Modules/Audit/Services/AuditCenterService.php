<?php

namespace App\Modules\Audit\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AuditCenterService
{
    /**
     * @param  array{module?: string|null, action?: string|null, status?: string|null, entity_type?: string|null, actor_id?: int|null, search?: string|null}  $filters
     * @return array{logs: LengthAwarePaginator, filters: array<string, mixed>, modules: list<string>, statuses: list<string>, entity_types: list<string>}
     */
    public function list(array $filters = [], int $perPage = 30): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [
                'logs' => AuditLog::query()->whereRaw('1 = 0')->paginate($perPage),
                'filters' => $filters,
                'modules' => [],
                'statuses' => [AuditLog::STATUS_SUCCESS, AuditLog::STATUS_FAILED],
                'entity_types' => [],
            ];
        }

        $module = trim((string) ($filters['module'] ?? ''));
        $action = trim((string) ($filters['action'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $actorId = isset($filters['actor_id']) && $filters['actor_id'] !== '' && $filters['actor_id'] !== null
            ? (int) $filters['actor_id']
            : null;

        $logs = AuditLog::query()
            ->with(['actor:id,name,full_name,email'])
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->when($action !== '', fn ($query) => $query->where('action', 'like', '%'.$action.'%'))
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($entityType !== '', fn ($query) => $query->where('entity_type', $entityType))
            ->when($actorId !== null, fn ($query) => $query->where('actor_id', $actorId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%')
                        ->orWhere('module', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $inner->orWhere('entity_id', (int) $search)
                            ->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => $this->serialize($log));

        return [
            'logs' => $logs,
            'filters' => [
                'module' => $module,
                'action' => $action,
                'status' => $status,
                'entity_type' => $entityType,
                'actor_id' => $actorId,
                'search' => $search,
            ],
            'modules' => [
                AuditLog::MODULE_ORDERS,
                AuditLog::MODULE_SUPPORT,
                AuditLog::MODULE_WALLETS,
                AuditLog::MODULE_SETTLEMENTS,
                AuditLog::MODULE_APPROVALS,
                AuditLog::MODULE_SYSTEM,
            ],
            'statuses' => [AuditLog::STATUS_SUCCESS, AuditLog::STATUS_FAILED],
            'entity_types' => [
                AuditLog::ENTITY_ORDER,
                AuditLog::ENTITY_SUPPORT_TICKET,
                AuditLog::ENTITY_PROVIDER_WALLET,
                AuditLog::ENTITY_SETTLEMENT,
                AuditLog::ENTITY_APPROVAL,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'module' => $log->module,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'subject' => $log->subject,
            'status' => $log->status,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'context' => $log->context,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->toIso8601String(),
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->full_name ?: $log->actor->name,
                'email' => $log->actor->email,
            ] : null,
            'entity_url' => $this->entityUrl($log->entity_type, $log->entity_id),
        ];
    }

    private function entityUrl(?string $entityType, ?int $entityId): ?string
    {
        if ($entityType === null || $entityId === null) {
            return null;
        }

        return match ($entityType) {
            AuditLog::ENTITY_ORDER => route('admin.orders.show', $entityId, absolute: false),
            AuditLog::ENTITY_SUPPORT_TICKET => route('admin.support.show', $entityId, absolute: false),
            AuditLog::ENTITY_PROVIDER_WALLET => route('admin.provider-wallets.show', $entityId, absolute: false),
            AuditLog::ENTITY_SETTLEMENT => route('admin.settlements.show', $entityId, absolute: false),
            AuditLog::ENTITY_APPROVAL => route('admin.approvals.show', $entityId, absolute: false),
            default => null,
        };
    }
}
