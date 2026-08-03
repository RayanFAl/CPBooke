<?php

namespace App\Modules\Admin\Support\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketResolutionReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportResolutionExportService
{
    public function __construct(
        private readonly SupportResolutionReportService $supportResolutionReportService,
    ) {}

    public function isAvailable(): bool
    {
        return $this->supportResolutionReportService->isAvailable();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        if (! $this->isAvailable()) {
            return $this->emptyDashboard($filters);
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $reports = $this->baseReportQuery($normalizedFilters);
        $totalReports = (clone $reports)->count();

        return [
            'available' => true,
            'filters' => $normalizedFilters,
            'summary' => [
                'total_reports' => $totalReports,
                'average_handling_minutes' => round((float) (clone $reports)->avg('handling_minutes'), 2),
                'reopen_rate' => $this->percentage(
                    (clone $reports)->where('reopened_count', '>', 0)->count(),
                    $totalReports,
                ),
                'escalation_rate' => $this->percentage(
                    (clone $reports)->where('escalated', true)->count(),
                    $totalReports,
                ),
            ],
            'by_resolution_type' => $this->groupCounts($reports, 'resolution_type'),
            'by_status_after' => $this->groupCounts($reports, 'status_after'),
            'top_root_causes' => $this->topRootCauses($reports),
            'agent_performance' => $this->agentPerformance($reports),
            'recent_reports' => $this->recentReports($normalizedFilters),
            'filter_options' => $this->filterOptions(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        if (! $this->isAvailable()) {
            abort(404, 'Support resolution reports are unavailable.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $rows = $this->baseReportQuery($normalizedFilters)
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->get();

        $filename = sprintf('support-resolution-reports-%s.csv', now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Ticket Number',
                'Ticket ID',
                'Customer Name',
                'Customer Email',
                'Order Reference',
                'Agent Name',
                'Agent Email',
                'Resolution Type',
                'Status Before',
                'Status After',
                'Root Cause',
                'Actions Taken',
                'Resolution Summary',
                'Customer Visible Notes',
                'Internal Notes',
                'Handling Minutes',
                'Escalated',
                'Reopened Count',
                'Satisfaction Requested',
                'Resolved At',
            ]);

            foreach ($rows as $report) {
                fputcsv($handle, $this->csvRow($report));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function printPayload(SupportTicket $ticket): array
    {
        $ticket->loadMissing([
            'user:id,name,full_name,email,phone,country',
            'order:id,booking_reference,external_booking_id,status,currency,total_amount',
            'assignee:id,name,full_name,email',
            'resolutionReport.agent:id,name,full_name,email',
        ]);

        $report = $ticket->resolutionReport;

        if ($report === null) {
            abort(404, 'No resolution report is available for this ticket.');
        }

        return [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'created_at' => $ticket->created_at?->toDateTimeString(),
                'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
                'closed_at' => $ticket->closed_at?->toDateTimeString(),
                'customer' => [
                    'name' => $ticket->user?->full_name ?: $ticket->user?->name,
                    'email' => $ticket->user?->email,
                    'phone' => $ticket->user?->phone,
                    'country' => $ticket->user?->country,
                ],
                'order' => $ticket->order ? [
                    'booking_reference' => $ticket->order->booking_reference,
                    'external_booking_id' => $ticket->order->external_booking_id,
                    'status' => $ticket->order->status,
                    'currency' => $ticket->order->currency,
                    'total_amount' => $ticket->order->total_amount,
                ] : null,
                'assignee' => $ticket->assignee ? [
                    'name' => $ticket->assignee->full_name ?: $ticket->assignee->name,
                    'email' => $ticket->assignee->email,
                ] : null,
            ],
            'report' => [
                'resolution_type' => $report->resolution_type,
                'root_cause' => $report->root_cause,
                'actions_taken' => $report->actions_taken,
                'resolution_summary' => $report->resolution_summary,
                'internal_notes' => $report->internal_notes,
                'customer_visible_notes' => $report->customer_visible_notes,
                'status_before' => $report->status_before,
                'status_after' => $report->status_after,
                'handling_minutes' => $report->handling_minutes,
                'escalated' => $report->escalated,
                'reopened_count' => $report->reopened_count,
                'satisfaction_requested' => $report->satisfaction_requested,
                'resolved_at' => $report->resolved_at?->toDateTimeString(),
                'agent' => $report->agent ? [
                    'name' => $report->agent->full_name ?: $report->agent->name,
                    'email' => $report->agent->email,
                ] : null,
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function emptyDashboard(array $filters): array
    {
        return [
            'available' => false,
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'total_reports' => 0,
                'average_handling_minutes' => 0,
                'reopen_rate' => 0,
                'escalation_rate' => 0,
            ],
            'by_resolution_type' => [],
            'by_status_after' => [],
            'top_root_causes' => [],
            'agent_performance' => [],
            'recent_reports' => [],
            'filter_options' => [
                'resolution_types' => [],
                'status_after' => [],
                'agents' => [],
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'status_after' => $filters['status_after'] ?? null,
            'resolution_type' => $filters['resolution_type'] ?? null,
            'agent_id' => isset($filters['agent_id']) ? (int) $filters['agent_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SupportTicketResolutionReport>
     */
    private function baseReportQuery(array $filters): Builder
    {
        $query = SupportTicketResolutionReport::query()
            ->with([
                'ticket:id,ticket_number,subject,status,user_id,order_id',
                'ticket.user:id,name,full_name,email',
                'ticket.order:id,booking_reference',
                'agent:id,name,full_name,email',
            ]);

        if ($filters['date_from']) {
            $query->whereDate('resolved_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('resolved_at', '<=', $filters['date_to']);
        }

        if ($filters['status_after']) {
            $query->where('status_after', $filters['status_after']);
        }

        if ($filters['resolution_type']) {
            $query->where('resolution_type', $filters['resolution_type']);
        }

        if ($filters['agent_id']) {
            $query->where('agent_id', $filters['agent_id']);
        }

        return $query;
    }

    /**
     * @param  Builder<SupportTicketResolutionReport>  $query
     * @return array<int, array<string, int|string>>
     */
    private function groupCounts(Builder $query, string $column): array
    {
        return (clone $query)
            ->selectRaw($column.' as bucket, COUNT(*) as aggregate')
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (object $row): array => [
                'key' => (string) $row->bucket,
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * @param  Builder<SupportTicketResolutionReport>  $query
     * @return array<int, array<string, int|string>>
     */
    private function topRootCauses(Builder $query, int $limit = 8): array
    {
        return (clone $query)
            ->selectRaw('root_cause, COUNT(*) as aggregate')
            ->whereNotNull('root_cause')
            ->groupBy('root_cause')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'root_cause' => (string) $row->root_cause,
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * @param  Builder<SupportTicketResolutionReport>  $query
     * @return array<int, array<string, int|string|float|null>>
     */
    private function agentPerformance(Builder $query, int $limit = 10): array
    {
        return (clone $query)
            ->select('agent_id')
            ->selectRaw('COUNT(*) as resolved_tickets')
            ->selectRaw('AVG(handling_minutes) as average_handling_minutes')
            ->with('agent:id,name,full_name,email')
            ->whereNotNull('agent_id')
            ->groupBy('agent_id')
            ->orderByDesc('resolved_tickets')
            ->limit($limit)
            ->get()
            ->map(fn (SupportTicketResolutionReport $report): array => [
                'agent_id' => $report->agent_id,
                'agent_name' => $report->agent?->full_name ?: $report->agent?->name ?: $report->agent?->email,
                'resolved_tickets' => (int) $report->resolved_tickets,
                'average_handling_minutes' => round((float) $report->average_handling_minutes, 2),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function recentReports(array $filters, int $limit = 15): array
    {
        return $this->baseReportQuery($filters)
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (SupportTicketResolutionReport $report): array => [
                'id' => $report->id,
                'ticket_id' => $report->ticket_id,
                'ticket_number' => $report->ticket?->ticket_number,
                'ticket_subject' => $report->ticket?->subject,
                'customer_name' => $report->ticket?->user?->full_name ?: $report->ticket?->user?->name,
                'agent_name' => $report->agent?->full_name ?: $report->agent?->name ?: $report->agent?->email,
                'resolution_type' => $report->resolution_type,
                'status_after' => $report->status_after,
                'handling_minutes' => $report->handling_minutes,
                'escalated' => $report->escalated,
                'resolved_at' => $report->resolved_at?->toDateTimeString(),
                'print_url' => $report->ticket_id
                    ? route('admin.support.resolution-report.print', $report->ticket_id, absolute: false)
                    : null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'resolution_types' => $this->supportResolutionReportService->resolutionTypeOptions(),
            'status_after' => $this->supportResolutionReportService->statusAfterOptions(),
            'agents' => $this->agentOptions(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function agentOptions(): array
    {
        if (! Schema::hasTable('support_ticket_resolution_reports')) {
            return [];
        }

        $agentIds = SupportTicketResolutionReport::query()
            ->whereNotNull('agent_id')
            ->distinct()
            ->pluck('agent_id');

        if ($agentIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $agentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'full_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function csvRow(SupportTicketResolutionReport $report): array
    {
        return [
            $report->ticket?->ticket_number,
            $report->ticket_id,
            $report->ticket?->user?->full_name ?: $report->ticket?->user?->name,
            $report->ticket?->user?->email,
            $report->ticket?->order?->booking_reference,
            $report->agent?->full_name ?: $report->agent?->name,
            $report->agent?->email,
            $report->resolution_type,
            $report->status_before,
            $report->status_after,
            $report->root_cause,
            $report->actions_taken,
            $report->resolution_summary,
            $report->customer_visible_notes,
            $report->internal_notes,
            $report->handling_minutes,
            $report->escalated ? 'yes' : 'no',
            $report->reopened_count,
            $report->satisfaction_requested ? 'yes' : 'no',
            $report->resolved_at?->toDateTimeString(),
        ];
    }

    private function percentage(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 2);
    }
}
