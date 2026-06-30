<?php

namespace App\Modules\Admin\Orders\Http\Controllers;

use App\Models\Order;
use App\Models\FinancialTransaction;
use App\Models\OrderHistory;
use App\Modules\Admin\Orders\Http\Requests\UpdateOrderNotesRequest;
use App\Modules\Admin\Orders\Http\Requests\UpdateOrderPaymentStatusRequest;
use App\Modules\Admin\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Admin\Orders\Services\OrderTicketPayloadBuilder;
use App\Modules\Api\Orders\Services\OrderService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderTicketPayloadBuilder $orderTicketPayloadBuilder,
    ) {
    }

    /**
     * Display the orders listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('orders.view');

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30', Rule::in(Order::statuses())],
            'search' => ['nullable', 'string', 'max:255'],
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
        $canViewHistory = Gate::forUser($actor)->allows('orders.view-history');
        $canViewFinancials = Gate::forUser($actor)->allows('finance.view')
            || Gate::forUser($actor)->allows('orders.financials.view');
        $hasOrderHistoryTable = Schema::hasTable('order_histories');
        $hasFinancialTransactionsTable = Schema::hasTable('financial_transactions');

        $order = $this->orderService->get($order);

        if ($canViewHistory && $hasOrderHistoryTable) {
            $order->loadMissing('histories.user');
        }

        if ($canViewFinancials && $hasFinancialTransactionsTable) {
            $order->loadMissing([
                'transactions' => fn ($query) => $query
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
            ]);
        }

        return Inertia::render('admin/orders/pages/Show', [
            'order' => $this->detailPayload(
                $order,
                $canViewFinancials && $hasFinancialTransactionsTable,
                $canChangeStatus,
                $canViewHistory && $hasOrderHistoryTable,
            ),
            'statuses' => $canChangeStatus ? $this->orderService->adminStatusOptions($order) : [],
            'payment_statuses' => $canChangeStatus ? $this->orderService->paymentStatusOptions() : [],
        ]);
    }

    /**
     * Update the order status.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('orders.change-status');

        $this->orderService->updateStatusByActor(
            $order,
            $request->validated('status'),
            $request->user(),
        );

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order status updated successfully.');
    }

    /**
     * Update the admin-only internal notes for the specified order.
     */
    public function updateNotes(UpdateOrderNotesRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('orders.update-notes');

        $this->orderService->updateInternalNotes(
            $order,
            $request->validated('internal_notes'),
            $request->user(),
        );

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Internal notes updated successfully.');
    }

    /**
     * Update the order payment status.
     */
    public function updatePaymentStatus(UpdateOrderPaymentStatusRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('orders.change-status');

        $this->orderService->updatePaymentStatusByActor(
            $order,
            $request->validated('payment_status'),
            $request->user(),
        );

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order payment status updated successfully.');
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
            'ticket_number' => $this->orderTicketPayloadBuilder->resolveTicketNumber($order),
            'provider_name' => $order->provider_name,
            'flight' => $this->listingFlightSummary($order),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'service_type' => $order->service_type,
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
    private function detailPayload(Order $order, bool $canViewFinancials, bool $canChangeStatus, bool $canViewHistory): array
    {
        return [
            'id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'ticket_number' => $this->orderTicketPayloadBuilder->resolveTicketNumber($order),
            'provider_name' => $order->provider_name,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'service_type' => $order->service_type,
            'details' => $order->details ?? [],
            'ticket' => $this->orderTicketPayloadBuilder->build($order, $canViewFinancials),
            'currency' => $canViewFinancials ? $order->currency : null,
            'total_amount' => $canViewFinancials ? $order->total_amount : null,
            'base_amount' => $canViewFinancials ? $order->base_amount : null,
            'tax_amount' => $canViewFinancials ? $order->tax_amount : null,
            'internal_notes' => $order->internal_notes,
            'error_message' => $order->error_message,
            'request_payload' => $canChangeStatus ? ($order->request_payload ?? []) : [],
            'response_payload' => $canChangeStatus ? ($order->response_payload ?? []) : [],
            'histories' => $canViewHistory
                ? $order->histories->map(fn (OrderHistory $history): array => [
                    'id' => $history->id,
                    'action' => $history->action,
                    'field' => $history->field,
                    'old_value' => $history->old_value,
                    'new_value' => $history->new_value,
                    'created_at' => $history->created_at?->toIso8601String(),
                    'user' => [
                        'id' => $history->user?->id,
                        'name' => $history->user?->full_name ?: $history->user?->name,
                        'email' => $history->user?->email,
                    ],
                ])->values()->all()
                : [],
            'transactions' => $canViewFinancials
                ? $order->transactions->map(fn (FinancialTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'source' => $transaction->source,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'financial_insight' => $canViewFinancials
                ? [
                    'net_amount' => $this->netTransactionAmount($order),
                    'currency' => $order->currency,
                ]
                : null,
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

    /**
     * Build a compact flight snapshot for the admin orders table.
     *
     * @return array<string, mixed>|null
     */
    private function listingFlightSummary(Order $order): ?array
    {
        $ticket = $this->orderTicketPayloadBuilder->build($order, false);
        $segments = is_array($ticket['segments'] ?? null) ? $ticket['segments'] : [];

        $origin = $ticket['origin'] ?? null;
        $destination = $ticket['destination'] ?? null;
        $departureTime = $ticket['departure_time'] ?? null;

        if ($segments !== []) {
            $firstSegment = is_array($segments[0] ?? null) ? $segments[0] : [];
            $lastSegment = is_array($segments[array_key_last($segments)] ?? null)
                ? $segments[array_key_last($segments)]
                : $firstSegment;

            $origin = $firstSegment['departure_airport'] ?? $origin;
            $destination = $lastSegment['arrival_airport'] ?? $destination;
            $departureTime = $firstSegment['departure_time'] ?? $departureTime;
        }

        $hasFlightData = ($ticket['pnr'] ?? null)
            || $origin
            || $destination
            || ($ticket['airline_code'] ?? null)
            || ($ticket['airline'] ?? null);

        if (! $hasFlightData) {
            return $order->provider_name
                ? ['provider_name' => $order->provider_name]
                : null;
        }

        return [
            'pnr' => $ticket['pnr'] ?? null,
            'origin' => $origin,
            'destination' => $destination,
            'departure_time' => $departureTime,
            'airline_code' => $ticket['airline_code'] ?? null,
            'airline' => $ticket['airline'] ?? null,
            'provider_name' => $order->provider_name,
        ];
    }

    /**
     * Build a simple net amount insight from the loaded order transactions.
     */
    private function netTransactionAmount(Order $order): string
    {
        $payments = (float) $order->transactions
            ->where('type', FinancialTransaction::TYPE_PAYMENT)
            ->sum('amount');

        $refunds = (float) $order->transactions
            ->where('type', FinancialTransaction::TYPE_REFUND)
            ->sum('amount');

        return number_format($payments - $refunds, 2, '.', '');
    }
}