<?php

namespace App\Modules\Admin\Support\Queries;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SupportInboxQuery
{
    /**
     * @return array<int, string>
     */
    public function indexSelectColumns(): array
    {
        $columns = [
            'id',
            'ticket_number',
            'user_id',
            'order_id',
            'category',
            'priority',
            'status',
            'assigned_to',
            'subject',
            'first_response_due_at',
            'resolution_due_at',
            'resolved_at',
            'created_at',
            'updated_at',
        ];

        if (Schema::hasColumn('support_tickets', 'first_response_at')) {
            $columns[] = 'first_response_at';
        }

        return $columns;
    }

    public function orderRelationSelectColumns(): string
    {
        $columns = [
            'id',
            'customer_id',
            'booking_reference',
            'external_booking_id',
            'provider_name',
            'status',
            'currency',
            'total_amount',
            'service_type',
            'details',
            'request_payload',
            'response_payload',
            'created_at',
        ];

        if (Schema::hasColumn('orders', 'payment_status')) {
            $columns[] = 'payment_status';
        }

        return implode(',', $columns);
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function applyFilter($query, string $column, mixed $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where($column, $value);
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function applySearch($query, ?string $search)
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $search = trim($search);

        return $query->where(function ($searchQuery) use ($search): void {
            if (ctype_digit($search)) {
                $searchQuery->orWhere('id', (int) $search);
            }

            $searchQuery
                ->orWhere('ticket_number', 'like', '%'.$search.'%')
                ->orWhereHas('user', function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
        });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function applySort($query, string $sort)
    {
        return match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'priority' => $query
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'medium' then 3 when 'low' then 4 else 5 end")
                ->orderByDesc('updated_at')
                ->orderByDesc('id'),
            'updated_at' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function applyQueueFilter($query, ?string $queue, ?int $agentId = null)
    {
        if ($queue === null || $queue === '' || $queue === 'all') {
            return $query;
        }

        return match ($queue) {
            'unread' => $this->applyUnreadFilter($query),
            'unassigned' => $query->whereNull('assigned_to'),
            'mine' => $agentId ? $query->where('assigned_to', $agentId) : $query,
            'sla_risk' => $this->applySlaRiskFilter($query),
            default => $query,
        };
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function applyUnreadFilter($query)
    {
        return $query->whereHas('latestMessage', function ($messageQuery): void {
            $messageQuery
                ->where('is_internal', false)
                ->whereHas('user', fn ($userQuery) => $userQuery->where('account_type', User::ACCOUNT_TYPE_CUSTOMER));
        });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function applySlaRiskFilter($query)
    {
        $now = now();

        return $query
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where(function ($slaQuery) use ($now): void {
                $slaQuery
                    ->where(function ($overdueQuery) use ($now): void {
                        $overdueQuery
                            ->where('first_response_due_at', '<=', $now)
                            ->orWhere('resolution_due_at', '<=', $now);
                    })
                    ->orWhere(function ($atRiskQuery) use ($now): void {
                        $atRiskQuery
                            ->whereBetween('first_response_due_at', [$now, $now->copy()->addHour()])
                            ->orWhereBetween('resolution_due_at', [$now, $now->copy()->addHour()]);
                    });
            });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return array<string, int>
     */
    public function buildCounters($query): array
    {
        return [
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'waiting_customer' => (clone $query)->where('status', 'waiting_customer')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'unread' => $this->applyUnreadFilter(clone $query)->count(),
            'unassigned' => (clone $query)->whereNull('assigned_to')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function emptyCounters(): array
    {
        return [
            'open' => 0,
            'in_progress' => 0,
            'waiting_customer' => 0,
            'resolved' => 0,
            'unread' => 0,
            'unassigned' => 0,
        ];
    }
}
