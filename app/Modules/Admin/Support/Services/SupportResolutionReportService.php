<?php

namespace App\Modules\Admin\Support\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketResolutionReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportResolutionReportService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('support_ticket_resolution_reports');
    }

    /**
     * @return array<int, array{name: string, label: string}>
     */
    public function resolutionTypeOptions(): array
    {
        return [
            ['name' => 'resolved', 'label' => 'Resolved'],
            ['name' => 'partially_resolved', 'label' => 'Partially Resolved'],
            ['name' => 'escalated', 'label' => 'Escalated'],
            ['name' => 'duplicate', 'label' => 'Duplicate'],
            ['name' => 'invalid', 'label' => 'Invalid'],
            ['name' => 'customer_cancelled', 'label' => 'Customer Cancelled'],
        ];
    }

    /**
     * @return array<int, array{name: string, label: string}>
     */
    public function statusAfterOptions(): array
    {
        return [
            ['name' => 'resolved', 'label' => 'Resolved'],
            ['name' => 'closed', 'label' => 'Closed'],
        ];
    }

    public function requiresResolutionReportForClose(SupportTicket $ticket): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $ticket->loadMissing('resolutionReport');

        return $ticket->resolutionReport === null || $ticket->resolutionReport->status_after !== 'closed';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveAndApplyStatus(SupportTicket $ticket, array $attributes, ?int $actorUserId, SupportService $supportService): SupportTicketResolutionReport
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Support ticket resolution reports table is not available.');
        }

        return DB::transaction(function () use ($ticket, $attributes, $actorUserId, $supportService): SupportTicketResolutionReport {
            $statusBefore = $ticket->status;
            $statusAfter = (string) $attributes['status_after'];

            if ($ticket->status !== $statusAfter) {
                $supportService->updateTicketStatus($ticket, $statusAfter, $actorUserId);
                $ticket->refresh();
            }

            $ticket->loadMissing('resolutionReport');

            $resolvedAt = $statusAfter === 'closed'
                ? ($ticket->closed_at ?? now())
                : ($ticket->resolved_at ?? now());

            $report = $ticket->resolutionReport ?? new SupportTicketResolutionReport([
                'ticket_id' => $ticket->id,
            ]);

            $report->forceFill([
                'agent_id' => $actorUserId ?? $ticket->assigned_to,
                'resolution_type' => $attributes['resolution_type'],
                'root_cause' => $attributes['root_cause'],
                'actions_taken' => $attributes['actions_taken'],
                'resolution_summary' => $attributes['resolution_summary'],
                'internal_notes' => $attributes['internal_notes'] ?? null,
                'customer_visible_notes' => $attributes['customer_visible_notes'] ?? null,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'handling_minutes' => $this->handlingMinutes($ticket, $resolvedAt),
                'escalated' => (bool) ($attributes['escalated'] ?? false) || $attributes['resolution_type'] === 'escalated',
                'reopened_count' => $this->reopenedCount($ticket),
                'satisfaction_requested' => (bool) ($attributes['satisfaction_requested'] ?? false),
                'metadata' => $this->normalizedMetadata($attributes['metadata'] ?? []),
                'resolved_at' => $resolvedAt,
            ])->save();

            return $report->fresh('agent');
        });
    }

    private function handlingMinutes(SupportTicket $ticket, mixed $resolvedAt): int
    {
        if (! $ticket->created_at || ! $resolvedAt) {
            return 0;
        }

        return max(0, $ticket->created_at->diffInMinutes($resolvedAt));
    }

    private function reopenedCount(SupportTicket $ticket): int
    {
        return $ticket->histories()
            ->where('action', 'status_changed')
            ->whereIn('old_value', ['resolved', 'closed'])
            ->whereIn('new_value', ['open', 'in_progress', 'waiting_customer'])
            ->count();
    }

    /**
     * @param  mixed  $metadata
     * @return array<string, mixed>
     */
    private function normalizedMetadata(mixed $metadata): array
    {
        return array_merge([
            'source' => 'support_resolution_report',
        ], is_array($metadata) ? $metadata : []);
    }
}