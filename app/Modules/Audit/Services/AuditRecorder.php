<?php

namespace App\Modules\Audit\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $module,
        string $action,
        string $subject,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
        string $status = AuditLog::STATUS_SUCCESS,
    ): ?AuditLog {
        if (! config('audit.enabled', true)) {
            return null;
        }

        try {
            if (! Schema::hasTable('audit_logs')) {
                return null;
            }

            $request = request();
            $resolvedActor = $actor ?? ($request?->user() instanceof User ? $request->user() : null);

            return AuditLog::query()->create([
                'actor_id' => $resolvedActor?->id,
                'module' => $module,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
                'subject' => mb_substr($subject, 0, 255),
                'status' => $status === AuditLog::STATUS_FAILED
                    ? AuditLog::STATUS_FAILED
                    : AuditLog::STATUS_SUCCESS,
                'old_values' => $oldValues === [] ? null : $oldValues,
                'new_values' => $newValues === [] ? null : $newValues,
                'context' => $context === [] ? null : $context,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $context
     */
    public function success(
        string $module,
        string $action,
        string $subject,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
    ): ?AuditLog {
        return $this->record(
            $module,
            $action,
            $subject,
            $entityType,
            $entityId,
            $actor,
            $oldValues,
            $newValues,
            $context,
            AuditLog::STATUS_SUCCESS,
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $context
     */
    public function failed(
        string $module,
        string $action,
        string $subject,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
    ): ?AuditLog {
        return $this->record(
            $module,
            $action,
            $subject,
            $entityType,
            $entityId,
            $actor,
            $oldValues,
            $newValues,
            $context,
            AuditLog::STATUS_FAILED,
        );
    }
}
