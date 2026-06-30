<?php

namespace App\Modules\Admin\Support\Queries;

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
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>
     */
    public function applyFilter($query, string $column, mixed $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where($column, $value);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>
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
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>
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
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>  $query
     * @return array<string, int>
     */
    public function buildCounters($query): array
    {
        return [
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'waiting_customer' => (clone $query)->where('status', 'waiting_customer')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
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
        ];
    }
}
