<?php

namespace App\Modules\Admin\Support\Http\Controllers;

use App\Models\SupportTicket;
use App\Modules\Admin\Support\Events\SupportMessageBroadcasted;
use App\Modules\Admin\Support\Events\SupportTicketUpdatedBroadcasted;
use App\Modules\Admin\Support\Http\Requests\CancelSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\CompensationSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\RefundSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\ReverseRefundSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\StoreSupportReplyRequest;
use App\Modules\Admin\Support\Http\Requests\StoreSupportTicketRequest;
use App\Modules\Admin\Support\Http\Requests\UpdateSupportTicketAssignmentRequest;
use App\Modules\Admin\Support\Http\Requests\UpdateSupportTicketStatusRequest;
use App\Modules\Admin\Support\Presenters\SupportAdminPresenter;
use App\Modules\Admin\Support\Queries\SupportInboxQuery;
use App\Modules\Admin\Support\Services\SupportResolutionReportService;
use App\Modules\Support\Services\SupportService;
use App\Modules\Support\Presenters\SupportChatPayloadBuilder;
use App\Modules\Admin\Support\SupportFormOptions;
use App\Modules\Api\Orders\Services\OrderActionService;
use App\Modules\Support\Services\SupportBroadcastService;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SupportController
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly SupportChatPayloadBuilder $supportChatPayloadBuilder,
        private readonly SupportAdminPresenter $supportAdminPresenter,
        private readonly SupportInboxQuery $supportInboxQuery,
        private readonly SupportFormOptions $supportFormOptions,
        private readonly SupportAttachmentStorage $supportAttachmentStorage,
        private readonly SupportResolutionReportService $supportResolutionReportService,
        private readonly OrderActionService $orderActionService,
        private readonly SupportBroadcastService $supportBroadcastService,
    ) {
    }

    /**
     * Display the support ticket inbox.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('support.view');

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'assigned_agent_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string'],
        ]);

        $filters = [
            'status' => $filters['status'] ?? null,
            'priority' => $filters['priority'] ?? null,
            'category' => $filters['category'] ?? null,
            'assigned_agent_id' => isset($filters['assigned_agent_id']) ? (int) $filters['assigned_agent_id'] : null,
            'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
            'order_id' => isset($filters['order_id']) ? (int) $filters['order_id'] : null,
            'search' => $filters['search'] ?? null,
            'sort' => $filters['sort'] ?? 'latest',
        ];

        if (! Schema::hasTable('support_tickets')) {
            return Inertia::render('admin/support/pages/Index', [
                'tickets' => [
                    'data' => [],
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'links' => [],
                ],
                'filters' => $filters,
                'counters' => $this->supportInboxQuery->emptyCounters(),
                'status_options' => array_filter(SupportFormOptions::STATUS_OPTIONS, fn (array $status): bool => $status['name'] !== 'closed'),
                'priority_options' => SupportFormOptions::PRIORITY_OPTIONS,
                'category_options' => SupportFormOptions::CATEGORY_OPTIONS,
                'sort_options' => SupportFormOptions::SORT_OPTIONS,
                'agents' => [],
            ]);
        }

        $query = SupportTicket::query()
            ->with([
                'user:id,name,full_name,email',
                'order:id,booking_reference,external_booking_id,status',
                'assignee:id,name,full_name,email',
                'latestMessage.user:id,name,full_name,email,account_type',
            ])
            ->select($this->supportInboxQuery->indexSelectColumns());

        $query = $this->supportInboxQuery->applySearch($query, $filters['search']);
        $query = $this->supportInboxQuery->applyFilter($query, 'priority', $filters['priority']);
        $query = $this->supportInboxQuery->applyFilter($query, 'category', $filters['category']);
        $query = $this->supportInboxQuery->applyFilter($query, 'assigned_to', $filters['assigned_agent_id']);
        $query = $this->supportInboxQuery->applyFilter($query, 'user_id', $filters['user_id']);
        $query = $this->supportInboxQuery->applyFilter($query, 'order_id', $filters['order_id']);

        $query = $this->supportInboxQuery->applyFilter($query, 'status', $filters['status']);
        $query = $this->supportInboxQuery->applySort($query, $filters['sort']);

        /** @var LengthAwarePaginator $tickets */
        $tickets = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => $this->supportAdminPresenter->summary($ticket));

        return Inertia::render('admin/support/pages/Index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'counters' => $this->supportInboxQuery->buildCounters(SupportTicket::query()),
            'status_options' => array_filter(SupportFormOptions::STATUS_OPTIONS, fn (array $status): bool => $status['name'] !== 'closed'),
            'priority_options' => SupportFormOptions::PRIORITY_OPTIONS,
            'category_options' => SupportFormOptions::CATEGORY_OPTIONS,
            'sort_options' => SupportFormOptions::SORT_OPTIONS,
            'agents' => $this->supportFormOptions->agents(),
        ]);
    }

    /**
     * Display the create support ticket page.
     */
    public function create(): Response
    {
        Gate::authorize('support.view');

        return Inertia::render('admin/support/pages/Create', [
            'customers' => $this->supportFormOptions->customers(),
            'orders' => $this->supportFormOptions->orders(),
            'agents' => $this->supportFormOptions->agents(),
            'categories' => SupportFormOptions::CATEGORY_OPTIONS,
            'priorities' => SupportFormOptions::PRIORITY_OPTIONS,
        ]);
    }

    /**
     * Store a new support ticket and its opening message.
     */
    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        Gate::authorize('support.view');

        $ticket = $this->supportService->createTicket([
            'user_id' => $request->integer('user_id'),
            'order_id' => $request->integer('order_id') ?: null,
            'category' => $request->string('category')->value(),
            'priority' => $request->string('priority')->value(),
            'status' => 'open',
            'assigned_to' => $request->integer('assigned_agent_id') ?: null,
            'subject' => $request->string('subject')->value(),
        ], $request->string('first_message')->value(), $request->user()?->id);

        return redirect()
            ->route('admin.support.show', $ticket)
            ->with('success', 'Support ticket created successfully.');
    }

    /**
     * Display a single support ticket.
     */
    public function show(Request $request, SupportTicket $supportTicket): Response
    {
        Gate::authorize('support.view');

        $canViewOrderFinancials = Gate::forUser($request->user())->allows('finance.view')
            || Gate::forUser($request->user())->allows('orders.financials.view');

        $inboxFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
        ]);

        $supportTicketRelations = [
            'user:id,name,full_name,email,phone,country,created_at',
            'assignee:id,name,full_name,email',
            'order:'.$this->supportInboxQuery->orderRelationSelectColumns(),
            'order.customer:id,name,full_name,email,phone',
            'order.transactions',
            'order.histories.user:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
            'messages.user:id,name,full_name,email,account_type',
            'histories.user:id,name,full_name,email',
        ];

        if ($this->supportAdminPresenter->resolutionReportsAvailable()) {
            $supportTicketRelations[] = 'resolutionReport.agent:id,name,full_name,email';
        }

        $supportTicket->loadMissing($supportTicketRelations);

        $inboxQuery = SupportTicket::query()
            ->with([
                'user:id,name,full_name,email',
                'order:id,booking_reference,external_booking_id,status',
                'assignee:id,name,full_name,email',
                'latestMessage.user:id,name,full_name,email,account_type',
            ])
            ->select($this->supportInboxQuery->indexSelectColumns());

        $inboxQuery = $this->supportInboxQuery->applySearch($inboxQuery, $inboxFilters['search'] ?? null);
        $inboxQuery = $this->supportInboxQuery->applyFilter($inboxQuery, 'status', $inboxFilters['status'] ?? null);
        $inboxQuery = $this->supportInboxQuery->applySort($inboxQuery, 'updated_at');

        $inboxTickets = $inboxQuery
            ->limit(18)
            ->get()
            ->map(fn (SupportTicket $ticket): array => $this->supportAdminPresenter->summary($ticket))
            ->values()
            ->all();

        return Inertia::render('admin/support/pages/Show', [
            'ticket' => $this->supportAdminPresenter->detail($supportTicket, $canViewOrderFinancials),
            'inbox' => [
                'tickets' => $inboxTickets,
                'filters' => [
                    'search' => $inboxFilters['search'] ?? '',
                    'status' => $inboxFilters['status'] ?? '',
                ],
                'selected_id' => $supportTicket->id,
            ],
            'status_options' => SupportFormOptions::STATUS_OPTIONS,
            'resolution_reports_enabled' => $this->supportAdminPresenter->resolutionReportsAvailable(),
            'resolution_type_options' => $this->supportAdminPresenter->resolutionReportsAvailable()
                ? $this->supportResolutionReportService->resolutionTypeOptions()
                : [],
            'resolution_status_options' => $this->supportAdminPresenter->resolutionReportsAvailable()
                ? $this->supportResolutionReportService->statusAfterOptions()
                : [],
            'agents' => $this->supportFormOptions->agents(),
            'order_actions' => [
                'can_manage' => $this->orderActionService->canViewSupportActions($supportTicket->order, $request->user()),
                'available' => $this->orderActionService->availableSupportActions($supportTicket->order, $request->user()),
            ],
            'notification_logs_enabled' => Schema::hasTable('notification_logs'),
            'customer_notification_logs' => $this->supportAdminPresenter->customerNotificationLogs($supportTicket),
        ]);
    }

    /**
     * Store a support reply for the specified ticket.
     */
    public function reply(StoreSupportReplyRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->supportService->replyToTicket(
            $supportTicket,
            $request->string('message')->value(),
            $request->user()?->id,
            $this->supportAttachmentStorage->store($request->file('attachment')),
        );

        $supportTicket->loadMissing([
            'assignee:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
            'messages.user:id,name,full_name,email,account_type',
        ]);

        $latestMessage = $supportTicket->messages->last();

        if ($latestMessage !== null) {
            $this->supportBroadcastService->dispatch(new SupportMessageBroadcasted(
                $supportTicket->id,
                $this->supportChatPayloadBuilder->ticket($supportTicket),
                $this->supportChatPayloadBuilder->message($latestMessage),
            ));
        }

        $this->supportBroadcastService->dispatch(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->supportChatPayloadBuilder->ticket($supportTicket),
        ));

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Reply added successfully.');
    }

    /**
     * Update the ticket status.
     */
    public function updateStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $newStatus = $request->string('status')->value();

        if ($newStatus === 'closed' && $supportTicket->status !== 'closed' && $this->supportResolutionReportService->requiresResolutionReportForClose($supportTicket)) {
            return back()
                ->withErrors([
                    'status' => 'A resolution report with a closed outcome is required before closing this ticket.',
                ])
                ->withInput();
        }

        $this->supportService->updateTicketStatus(
            $supportTicket,
            $newStatus,
            $request->user()?->id,
        );

        $supportTicket->loadMissing([
            'assignee:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
        ]);

        $this->supportBroadcastService->dispatch(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->supportChatPayloadBuilder->ticket($supportTicket),
        ));

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Ticket status updated successfully.');
    }

    /**
     * Update the assigned agent for the ticket.
     */
    public function assign(UpdateSupportTicketAssignmentRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->supportService->assignTicket(
            $supportTicket,
            $request->integer('assigned_agent_id') ?: null,
            $request->user()?->id,
        );

        $supportTicket->loadMissing([
            'assignee:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
        ]);

        $this->supportBroadcastService->dispatch(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->supportChatPayloadBuilder->ticket($supportTicket),
        ));

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Ticket assignment updated successfully.');
    }

    /**
     * Cancel the linked order from the support workspace.
     */
    public function cancelOrder(CancelSupportOrderRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->orderActionService->cancelFromSupport(
            $supportTicket,
            $request->user(),
            $request->string('reason')->value(),
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Order cancelled successfully from support.');
    }

    /**
     * Apply a full refund to the linked order from the support workspace.
     */
    public function fullRefund(RefundSupportOrderRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->orderActionService->fullRefundFromSupport(
            $supportTicket,
            $request->user(),
            $request->string('reason')->value(),
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Full refund applied successfully from support.');
    }

    /**
     * Apply a partial refund to the linked order from the support workspace.
     */
    public function partialRefund(RefundSupportOrderRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->orderActionService->partialRefundFromSupport(
            $supportTicket,
            $request->user(),
            (float) $request->input('amount'),
            $request->string('reason')->value(),
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Partial refund applied successfully from support.');
    }

    /**
     * Reverse a previously applied refund from the support workspace.
     */
    public function reverseRefund(ReverseRefundSupportOrderRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->orderActionService->reverseRefundFromSupport(
            $supportTicket,
            $request->user(),
            $request->string('reason')->value(),
            $request->string('internal_note')->value(),
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Refund reversed successfully from support.');
    }

    /**
     * Add compensation to the linked order from the support workspace.
     */
    public function compensation(CompensationSupportOrderRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        $this->orderActionService->compensationFromSupport(
            $supportTicket,
            $request->user(),
            (float) $request->input('amount'),
            $request->string('reason')->value(),
            $request->string('compensation_type')->value(),
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', 'Compensation applied successfully from support.');
    }
}
