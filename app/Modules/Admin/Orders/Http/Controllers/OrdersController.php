<?php

namespace App\Modules\Admin\Orders\Http\Controllers;

use App\Models\Order;
use App\Modules\Admin\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Api\Orders\Services\OrderService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Display the orders listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('orders.view');

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $actor = $request->user();
        $canViewFinancials = Gate::forUser($actor)->allows('finance.view')
            || Gate::forUser($actor)->allows('orders.financials.view');

        return Inertia::render('admin/orders/pages/Index', [
            'orders' => $this->orderService
                ->paginateForAdmin($filters)
                ->through(fn (Order $order): array => $this->summaryPayload($order, $canViewFinancials)),
            'filters' => $filters,
            'statuses' => $this->orderService->statusOptions(),
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('orders.view');

        $actor = $request->user();
        $canChangeStatus = Gate::forUser($actor)->allows('orders.change-status');
        $canViewFinancials = Gate::forUser($actor)->allows('finance.view')
            || Gate::forUser($actor)->allows('orders.financials.view');

        return Inertia::render('admin/orders/pages/Show', [
            'order' => $this->detailPayload(
                $this->orderService->get($order),
                $canViewFinancials,
                $canChangeStatus,
            ),
            'statuses' => $this->orderService->adminStatusOptions(),
        ]);
    }

    /**
     * Update the order status.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('orders.change-status');

        $this->orderService->updateStatus(
            $order,
            $request->validated('status'),
        );

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order status updated successfully.');
    }

    /**
     * Build the listing payload for the admin orders table.
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(Order $order, bool $canViewFinancials): array
    {
        return [
            'id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'external_booking_id' => $order->external_booking_id,
            'provider_name' => $order->provider_name,
            'status' => $order->status,
            'currency' => $canViewFinancials ? $order->currency : null,
            'total_amount' => $canViewFinancials ? $order->total_amount : null,
            'customer' => [
                'id' => $order->customer?->id,
                'name' => $order->customer?->full_name ?: $order->customer?->name,
                'email' => $order->customer?->email,
            ],
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build the detail payload for the admin order page.
     *
     * @return array<string, mixed>
     */
    private function detailPayload(Order $order, bool $canViewFinancials, bool $canChangeStatus): array
    {
        return [
            'id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'external_booking_id' => $order->external_booking_id,
            'provider_name' => $order->provider_name,
            'status' => $order->status,
            'currency' => $canViewFinancials ? $order->currency : null,
            'total_amount' => $canViewFinancials ? $order->total_amount : null,
            'error_message' => $order->error_message,
            'request_payload' => $canChangeStatus ? ($order->request_payload ?? []) : [],
            'response_payload' => $canChangeStatus ? ($order->response_payload ?? []) : [],
            'customer' => [
                'id' => $order->customer?->id,
                'name' => $order->customer?->full_name ?: $order->customer?->name,
                'email' => $order->customer?->email,
                'phone' => $order->customer?->phone,
            ],
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}