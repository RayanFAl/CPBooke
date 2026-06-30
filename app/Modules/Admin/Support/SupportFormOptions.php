<?php

namespace App\Modules\Admin\Support;

use App\Models\Order;
use App\Models\User;

class SupportFormOptions
{
    public const STATUS_OPTIONS = [
        ['name' => 'open', 'label' => 'Open'],
        ['name' => 'in_progress', 'label' => 'In Progress'],
        ['name' => 'waiting_customer', 'label' => 'Waiting Customer'],
        ['name' => 'resolved', 'label' => 'Resolved'],
        ['name' => 'closed', 'label' => 'Closed'],
    ];

    public const CATEGORY_OPTIONS = [
        ['name' => 'booking_change', 'label' => 'Booking Change'],
        ['name' => 'refund_request', 'label' => 'Refund Request'],
        ['name' => 'technical_issue', 'label' => 'Technical Issue'],
        ['name' => 'payment_issue', 'label' => 'Payment Issue'],
        ['name' => 'document_request', 'label' => 'Document Request'],
    ];

    public const PRIORITY_OPTIONS = [
        ['name' => 'low', 'label' => 'Low'],
        ['name' => 'medium', 'label' => 'Medium'],
        ['name' => 'high', 'label' => 'High'],
        ['name' => 'urgent', 'label' => 'Urgent'],
    ];

    public const SORT_OPTIONS = [
        ['name' => 'latest', 'label' => 'Latest'],
        ['name' => 'oldest', 'label' => 'Oldest'],
        ['name' => 'priority', 'label' => 'Priority'],
        ['name' => 'updated_at', 'label' => 'Recently Updated'],
    ];

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function customers(): array
    {
        return User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->select(['id', 'name', 'full_name', 'email'])
            ->orderBy('full_name')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function orders(): array
    {
        return Order::query()
            ->with('customer:id,name,full_name,email')
            ->select(['id', 'customer_id', 'booking_reference', 'external_booking_id'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'user_id' => $order->customer_id,
                'reference' => $order->booking_reference ?: $order->external_booking_id ?: 'Order #'.$order->id,
                'customer' => $order->customer?->full_name ?: $order->customer?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function agents(): array
    {
        return User::query()
            ->where('account_type', User::ACCOUNT_TYPE_ADMIN)
            ->select(['id', 'name', 'full_name', 'email'])
            ->orderBy('full_name')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }
}
