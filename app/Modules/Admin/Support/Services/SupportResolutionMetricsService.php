<?php

namespace App\Modules\Admin\Support\Services;

use App\Models\SupportTicketResolutionReport;
use Illuminate\Support\Facades\DB;

class SupportResolutionMetricsService
{
    public function averageResolutionTimeMinutes(): float
    {
        return round((float) SupportTicketResolutionReport::query()->avg('handling_minutes'), 2);
    }

    public function reopenRate(): float
    {
        $total = max(1, SupportTicketResolutionReport::query()->count());
        $reopened = SupportTicketResolutionReport::query()->where('reopened_count', '>', 0)->count();

        return round(($reopened / $total) * 100, 2);
    }

    public function escalationRate(): float
    {
        $total = max(1, SupportTicketResolutionReport::query()->count());
        $escalated = SupportTicketResolutionReport::query()->where('escalated', true)->count();

        return round(($escalated / $total) * 100, 2);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topRootCauses(int $limit = 10): array
    {
        return SupportTicketResolutionReport::query()
            ->selectRaw('root_cause, COUNT(*) as aggregate')
            ->whereNotNull('root_cause')
            ->groupBy('root_cause')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'root_cause' => $row->root_cause,
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agentResolutionPerformance(int $limit = 20): array
    {
        return SupportTicketResolutionReport::query()
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
     * @return array<int, array<string, mixed>>
     */
    public function ticketsByResolutionType(): array
    {
        return SupportTicketResolutionReport::query()
            ->selectRaw('resolution_type, COUNT(*) as aggregate')
            ->groupBy('resolution_type')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (object $row): array => [
                'resolution_type' => $row->resolution_type,
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }
}