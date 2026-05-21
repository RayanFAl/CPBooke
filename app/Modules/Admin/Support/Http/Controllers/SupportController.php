<?php

namespace App\Modules\Admin\Support\Http\Controllers;

use App\Models\Order;
use App\Models\FinancialTransaction;
use App\Models\OrderHistory;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketHistory;
use App\Models\User;
use App\Models\SupportTicketResolutionReport;
use App\Modules\Admin\Support\Events\SupportMessageBroadcasted;
use App\Modules\Admin\Support\Events\SupportTicketUpdatedBroadcasted;
use App\Modules\Admin\Support\SupportChatPayloadBuilder;
use App\Modules\Admin\Support\Http\Requests\CancelSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\CompensationSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\RefundSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\ReverseRefundSupportOrderRequest;
use App\Modules\Admin\Support\Http\Requests\StoreSupportReplyRequest;
use App\Modules\Admin\Support\Http\Requests\StoreSupportTicketRequest;
use App\Modules\Admin\Support\Http\Requests\UpdateSupportTicketAssignmentRequest;
use App\Modules\Admin\Support\Http\Requests\UpdateSupportTicketStatusRequest;
use App\Modules\Admin\Support\Services\SupportResolutionReportService;
use App\Modules\Admin\Support\Services\SupportService;
use App\Modules\Api\Orders\Services\OrderActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportController
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly SupportChatPayloadBuilder $supportChatPayloadBuilder,
        private readonly SupportResolutionReportService $supportResolutionReportService,
        private readonly OrderActionService $orderActionService,
    ) {
    }

    private const STATUS_OPTIONS = [
        ['name' => 'open', 'label' => 'Open'],
        ['name' => 'in_progress', 'label' => 'In Progress'],
        ['name' => 'waiting_customer', 'label' => 'Waiting Customer'],
        ['name' => 'resolved', 'label' => 'Resolved'],
        ['name' => 'closed', 'label' => 'Closed'],
    ];

    private const CATEGORY_OPTIONS = [
        ['name' => 'booking_change', 'label' => 'Booking Change'],
        ['name' => 'refund_request', 'label' => 'Refund Request'],
        ['name' => 'technical_issue', 'label' => 'Technical Issue'],
        ['name' => 'payment_issue', 'label' => 'Payment Issue'],
        ['name' => 'document_request', 'label' => 'Document Request'],
    ];

    private const PRIORITY_OPTIONS = [
        ['name' => 'low', 'label' => 'Low'],
        ['name' => 'medium', 'label' => 'Medium'],
        ['name' => 'high', 'label' => 'High'],
        ['name' => 'urgent', 'label' => 'Urgent'],
    ];

    private const SORT_OPTIONS = [
        ['name' => 'latest', 'label' => 'Latest'],
        ['name' => 'oldest', 'label' => 'Oldest'],
        ['name' => 'priority', 'label' => 'Priority'],
        ['name' => 'updated_at', 'label' => 'Recently Updated'],
    ];

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
                'counters' => $this->emptyCounters(),
                'status_options' => array_filter(self::STATUS_OPTIONS, fn (array $status): bool => $status['name'] !== 'closed'),
                'priority_options' => self::PRIORITY_OPTIONS,
                'category_options' => self::CATEGORY_OPTIONS,
                'sort_options' => self::SORT_OPTIONS,
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
            ->select($this->indexSelectColumns());

        $query = $this->applyInboxSearch($query, $filters['search']);
        $query = $this->applyInboxFilter($query, 'priority', $filters['priority']);
        $query = $this->applyInboxFilter($query, 'category', $filters['category']);
        $query = $this->applyInboxFilter($query, 'assigned_to', $filters['assigned_agent_id']);
        $query = $this->applyInboxFilter($query, 'user_id', $filters['user_id']);
        $query = $this->applyInboxFilter($query, 'order_id', $filters['order_id']);

        $query = $this->applyInboxFilter($query, 'status', $filters['status']);
        $query = $this->applySort($query, $filters['sort']);

        /** @var LengthAwarePaginator $tickets */
        $tickets = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => $this->summaryPayload($ticket));

        return Inertia::render('admin/support/pages/Index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'counters' => $this->buildCounters(SupportTicket::query()),
            'status_options' => array_filter(self::STATUS_OPTIONS, fn (array $status): bool => $status['name'] !== 'closed'),
            'priority_options' => self::PRIORITY_OPTIONS,
            'category_options' => self::CATEGORY_OPTIONS,
            'sort_options' => self::SORT_OPTIONS,
            'agents' => $this->agentOptions(),
        ]);
    }

    /**
     * Display the create support ticket page.
     */
    public function create(): Response
    {
        Gate::authorize('support.view');

        return Inertia::render('admin/support/pages/Create', [
            'customers' => $this->customerOptions(),
            'orders' => $this->orderOptions(),
            'agents' => $this->agentOptions(),
            'categories' => self::CATEGORY_OPTIONS,
            'priorities' => self::PRIORITY_OPTIONS,
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

        $inboxFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
        ]);

        $supportTicketRelations = [
            'user:id,name,full_name,email,phone,country,created_at',
            'assignee:id,name,full_name,email',
            'order:'.$this->orderRelationSelectColumns(),
            'order.transactions',
            'order.histories.user:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
            'messages.user:id,name,full_name,email,account_type',
            'histories.user:id,name,full_name,email',
        ];

        if ($this->resolutionReportsAvailable()) {
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
            ->select($this->indexSelectColumns());

        $inboxQuery = $this->applyInboxSearch($inboxQuery, $inboxFilters['search'] ?? null);
        $inboxQuery = $this->applyInboxFilter($inboxQuery, 'status', $inboxFilters['status'] ?? null);
        $inboxQuery = $this->applySort($inboxQuery, 'updated_at');

        $inboxTickets = $inboxQuery
            ->limit(18)
            ->get()
            ->map(fn (SupportTicket $ticket): array => $this->summaryPayload($ticket))
            ->values()
            ->all();

        return Inertia::render('admin/support/pages/Show', [
            'ticket' => $this->detailPayload($supportTicket),
            'inbox' => [
                'tickets' => $inboxTickets,
                'filters' => [
                    'search' => $inboxFilters['search'] ?? '',
                    'status' => $inboxFilters['status'] ?? '',
                ],
                'selected_id' => $supportTicket->id,
            ],
            'status_options' => self::STATUS_OPTIONS,
            'resolution_reports_enabled' => $this->resolutionReportsAvailable(),
            'resolution_type_options' => $this->resolutionReportsAvailable()
                ? $this->supportResolutionReportService->resolutionTypeOptions()
                : [],
            'resolution_status_options' => $this->resolutionReportsAvailable()
                ? $this->supportResolutionReportService->statusAfterOptions()
                : [],
            'agents' => $this->agentOptions(),
            'order_actions' => [
                'can_manage' => $this->orderActionService->canViewSupportActions($supportTicket->order, $request->user()),
                'available' => $this->orderActionService->availableSupportActions($supportTicket->order, $request->user()),
            ],
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
            $this->storeAttachment($request->file('attachment')),
        );

        $supportTicket->loadMissing([
            'assignee:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
            'messages.user:id,name,full_name,email,account_type',
        ]);

        $latestMessage = $supportTicket->messages->last();

        if ($latestMessage !== null) {
            event(new SupportMessageBroadcasted(
                $supportTicket->id,
                $this->supportChatPayloadBuilder->ticket($supportTicket),
                $this->supportChatPayloadBuilder->message($latestMessage),
            ));
        }

        event(new SupportTicketUpdatedBroadcasted(
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

        event(new SupportTicketUpdatedBroadcasted(
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

        event(new SupportTicketUpdatedBroadcasted(
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

    /**
     * Build the admin listing payload for a support ticket.
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(SupportTicket $ticket): array
    {
        $lastMessage = $ticket->relationLoaded('latestMessage')
            ? $ticket->latestMessage
            : $ticket->messages->last();

        $lastSenderType = $lastMessage?->user?->isAdminAccount() ? 'agent' : ($lastMessage ? 'user' : null);

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'last_message' => $lastMessage?->message,
            'last_message_at' => $lastMessage?->created_at?->toDateTimeString(),
            'last_sender_type' => $lastSenderType,
            'has_unread_for_admin' => $lastSenderType === 'user',
            'has_unread_for_customer' => $lastSenderType === 'agent',
            'conversation_state' => $this->conversationState($lastSenderType),
            'sla_status' => $this->supportService->slaStatusFor($ticket),
            'sla_risk' => $this->supportService->slaRiskFor($ticket),
            'agent_workload_percentage' => $this->supportService->agentWorkloadPercentageFor($ticket->assignee),
            'updated_at' => $ticket->updated_at?->toDateTimeString(),
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'user' => [
                'id' => $ticket->user?->id,
                'name' => $ticket->user?->full_name ?: $ticket->user?->name,
                'email' => $ticket->user?->email,
            ],
            'assignee' => $ticket->assignee
                ? [
                    'id' => $ticket->assignee->id,
                    'name' => $ticket->assignee->full_name ?: $ticket->assignee->name,
                    'email' => $ticket->assignee->email,
                ]
                : null,
            'order' => $ticket->order
                ? [
                    'id' => $ticket->order->id,
                    'reference' => $ticket->order->booking_reference ?: $ticket->order->external_booking_id ?: 'Order #'.$ticket->order->id,
                    'status' => $ticket->order->status,
                ]
                : null,
        ];
    }

    /**
     * Build the support ticket detail payload.
     *
     * @return array<string, mixed>
     */
    private function detailPayload(SupportTicket $ticket): array
    {
        $lastMessage = $ticket->messages->last();
        $lastSenderType = $lastMessage?->user?->isAdminAccount() ? 'agent' : ($lastMessage ? 'user' : null);
        $resolutionReport = $this->resolutionReportsAvailable() ? $ticket->resolutionReport : null;

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'assigned_agent_id' => $ticket->assigned_to,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'last_message' => $lastMessage?->message,
            'last_message_at' => $lastMessage?->created_at?->toDateTimeString(),
            'last_sender_type' => $lastSenderType,
            'has_unread_for_admin' => $lastSenderType === 'user',
            'has_unread_for_customer' => $lastSenderType === 'agent',
            'conversation_state' => $this->conversationState($lastSenderType),
            'first_response_due_at' => $ticket->first_response_due_at?->toDateTimeString(),
            'resolution_due_at' => $ticket->resolution_due_at?->toDateTimeString(),
            'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
            'closed_at' => $ticket->closed_at?->toDateTimeString(),
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'updated_at' => $ticket->updated_at?->toDateTimeString(),
            'resolution_report' => $resolutionReport ? $this->resolutionReportPayload($resolutionReport) : null,
            'user' => [
                'id' => $ticket->user?->id,
                'name' => $ticket->user?->full_name ?: $ticket->user?->name,
                'email' => $ticket->user?->email,
                'phone' => $ticket->user?->phone,
                'country' => $ticket->user?->country,
                'created_at' => $ticket->user?->created_at?->toDateTimeString(),
            ],
            'assignee' => $ticket->assignee
                ? [
                    'id' => $ticket->assignee->id,
                    'name' => $ticket->assignee->full_name ?: $ticket->assignee->name,
                    'email' => $ticket->assignee->email,
                ]
                : null,
            'order' => $ticket->order
                ? [
                    'id' => $ticket->order->id,
                    'reference' => $ticket->order->booking_reference ?: $ticket->order->external_booking_id ?: 'Order #'.$ticket->order->id,
                    'provider_name' => $ticket->order->provider_name,
                    'status' => $ticket->order->status,
                    'payment_status' => Schema::hasColumn('orders', 'payment_status')
                        ? $ticket->order->payment_status
                        : null,
                    'currency' => $ticket->order->currency,
                    'total_amount' => $ticket->order->total_amount !== null
                        ? number_format((float) $ticket->order->total_amount, 2, '.', '')
                        : null,
                    'service_type' => $ticket->order->service_type,
                    'created_at' => $ticket->order->created_at?->toDateTimeString(),
                ]
                : null,
            'order_snapshot' => $ticket->order ? $this->orderSnapshotPayload($ticket->order) : null,
            'messages' => $ticket->messages
                ->map(fn (SupportMessage $message): array => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'is_internal' => $message->is_internal,
                    'sender_type' => $message->user?->isAdminAccount() ? 'agent' : 'user',
                    'attachment_path' => $message->attachment_path,
                    'attachment_name' => $message->attachment_name,
                    'attachment_mime' => $message->attachment_mime,
                    'attachment_size' => $message->attachment_size,
                    'attachment_url' => $this->attachmentUrl($message->attachment_path),
                    'has_attachment' => $message->attachment_path !== null,
                    'attachment_is_image' => str_starts_with((string) $message->attachment_mime, 'image/'),
                    'created_at' => $message->created_at?->toDateTimeString(),
                    'user' => [
                        'id' => $message->user?->id,
                        'name' => $message->user?->full_name ?: $message->user?->name,
                        'email' => $message->user?->email,
                    ],
                ])
                ->values()
                ->all(),
            'history' => $ticket->histories
                ->map(fn (SupportTicketHistory $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'field' => $entry->field,
                    'old_value' => $entry->old_value,
                    'new_value' => $entry->new_value,
                    'created_at' => $entry->created_at?->toDateTimeString(),
                    'user' => [
                        'id' => $entry->user?->id,
                        'name' => $entry->user?->full_name ?: $entry->user?->name,
                        'email' => $entry->user?->email,
                    ],
                ])
                ->values()
                ->all(),
            'timeline' => $this->timelinePayload($ticket),
        ];
    }

    /**
     * Build the selectable customer options for support creation.
     *
     * @return array<int, array<string, int|string|null>>
     */
    private function customerOptions(): array
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
     * Build the selectable order options for support creation.
     *
     * @return array<int, array<string, int|string|null>>
     */
    private function orderOptions(): array
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
     * Build the selectable support agent options.
     *
     * @return array<int, array<string, int|string|null>>
     */
    private function agentOptions(): array
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

    /**
     * Build the inbox select columns against the active support schema.
     *
     * @return array<int, string>
     */
    private function indexSelectColumns(): array
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

    /**
     * Build the order relation select list against the active orders schema.
     */
    private function orderRelationSelectColumns(): string
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
            'created_at',
        ];

        if (Schema::hasColumn('orders', 'payment_status')) {
            $columns[] = 'payment_status';
        }

        return implode(',', $columns);
    }

    /**
     * Build the order summary card shown in the support workspace.
     *
     * @return array<string, string|null>
     */
    private function orderSnapshotPayload(Order $order): array
    {
        $order->loadMissing('transactions');

        return [
            'reference' => $order->booking_reference ?: $order->external_booking_id ?: 'Order #'.$order->id,
            'order_total' => number_format((float) $order->total_amount, 2, '.', ''),
            'paid_amount' => number_format($order->getNetPaidAmount(), 2, '.', ''),
            'refunded_amount' => number_format($order->getRefundedAmount(), 2, '.', ''),
            'compensation_amount' => number_format($order->getCompensationAmount(), 2, '.', ''),
            'remaining_collectible' => number_format($order->getRemainingCollectibleAmount(), 2, '.', ''),
            'provider_name' => $order->provider_name,
            'payment_method' => $this->resolveOrderPaymentMethod($order),
            'currency' => $order->currency,
            'status' => $order->status,
            'payment_status' => $order->derivePaymentStatus(),
        ];
    }

    /**
     * Build a unified timeline from support, order, and finance events.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timelinePayload(SupportTicket $ticket): array
    {
        $events = collect();

        if ($this->resolutionReportsAvailable()) {
            $ticket->loadMissing('resolutionReport.agent');
        }

        if ($ticket->order !== null) {
            $order = $ticket->order;
            $order->loadMissing(['transactions', 'histories.user']);

            $financialActors = User::query()
                ->whereIn('id', $order->transactions->pluck('performed_by_id')->filter()->unique()->all())
                ->get(['id', 'name', 'full_name', 'email'])
                ->keyBy('id');

            $events->push([
                'id' => 'order-created-'.$order->id,
                'source' => 'order',
                'event' => 'Order Created',
                'description' => 'The linked order was created.',
                'actor' => $order->customer?->full_name ?: $order->customer?->name ?: 'System',
                'created_at' => $order->created_at?->toDateTimeString(),
                'amount' => null,
                'currency' => $order->currency,
            ]);

            $events = $events->merge(
                $order->histories->map(fn (OrderHistory $entry): array => [
                    'id' => 'order-history-'.$entry->id,
                    'source' => 'order',
                    'event' => $this->humanizeEventLabel($entry->action),
                    'description' => $this->orderHistoryDescription($entry),
                    'actor' => $entry->user?->full_name ?: $entry->user?->name ?: $entry->user?->email ?: 'System',
                    'created_at' => $entry->created_at?->toDateTimeString(),
                    'amount' => null,
                    'currency' => $order->currency,
                ])
            );

            $events = $events->merge(
                $order->transactions->map(function (FinancialTransaction $transaction) use ($financialActors, $order): array {
                    $actor = $financialActors->get($transaction->performed_by_id);

                    return [
                        'id' => 'financial-'.$transaction->id,
                        'source' => 'financial',
                        'event' => $this->financialTimelineLabel($transaction),
                        'description' => $this->financialTimelineDescription($transaction),
                        'actor' => $actor?->full_name ?: $actor?->name ?: $actor?->email ?: 'System',
                        'created_at' => $transaction->created_at?->toDateTimeString(),
                        'amount' => number_format((float) $transaction->amount, 2, '.', ''),
                        'currency' => $transaction->currency ?: $order->currency,
                    ];
                })
            );
        }

        $events->push([
            'id' => 'support-ticket-opened-'.$ticket->id,
            'source' => 'support',
            'event' => 'Support Ticket Opened',
            'description' => 'The support conversation was opened for this order context.',
            'actor' => $ticket->user?->full_name ?: $ticket->user?->name ?: $ticket->user?->email ?: 'System',
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'amount' => null,
            'currency' => $ticket->order?->currency,
        ]);

        $events = $events->merge(
            $ticket->histories->map(fn (SupportTicketHistory $entry): array => [
                'id' => 'support-history-'.$entry->id,
                'source' => 'support',
                'event' => $this->humanizeEventLabel($entry->action),
                'description' => $this->supportHistoryDescription($entry),
                'actor' => $entry->user?->full_name ?: $entry->user?->name ?: $entry->user?->email ?: 'System',
                'created_at' => $entry->created_at?->toDateTimeString(),
                'amount' => null,
                'currency' => $ticket->order?->currency,
            ])
        );

        if ($this->resolutionReportsAvailable() && $ticket->resolutionReport !== null) {
            $events->push([
                'id' => 'resolution-report-'.$ticket->resolutionReport->id,
                'source' => 'support',
                'event' => 'Ticket resolved by '.($ticket->resolutionReport->agent?->full_name ?: $ticket->resolutionReport->agent?->name ?: $ticket->resolutionReport->agent?->email ?: 'System'),
                'description' => $this->resolutionReportTimelineDescription($ticket->resolutionReport),
                'actor' => $ticket->resolutionReport->agent?->full_name ?: $ticket->resolutionReport->agent?->name ?: $ticket->resolutionReport->agent?->email ?: 'System',
                'created_at' => $ticket->resolutionReport->resolved_at?->toDateTimeString() ?: $ticket->resolutionReport->created_at?->toDateTimeString(),
                'amount' => null,
                'currency' => $ticket->order?->currency,
            ]);
        }

        return $events
            ->filter(fn (array $event): bool => filled($event['created_at']))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolutionReportPayload(SupportTicketResolutionReport $report): array
    {
        return [
            'id' => $report->id,
            'ticket_id' => $report->ticket_id,
            'agent_id' => $report->agent_id,
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
            'metadata' => $report->metadata ?? [],
            'resolved_at' => $report->resolved_at?->toDateTimeString(),
            'created_at' => $report->created_at?->toDateTimeString(),
            'updated_at' => $report->updated_at?->toDateTimeString(),
            'agent' => $report->agent ? [
                'id' => $report->agent->id,
                'name' => $report->agent->full_name ?: $report->agent->name,
                'email' => $report->agent->email,
            ] : null,
        ];
    }

    private function resolutionReportTimelineDescription(SupportTicketResolutionReport $report): string
    {
        return sprintf(
            'Resolution type: %s. Handling time: %d minutes. Summary: %s',
            Str::of($report->resolution_type)->replace('_', ' ')->lower()->toString(),
            $report->handling_minutes,
            $report->resolution_summary,
        );
    }

    private function resolutionReportsAvailable(): bool
    {
        return Schema::hasTable('support_ticket_resolution_reports');
    }

    private function resolveOrderPaymentMethod(Order $order): ?string
    {
        $details = is_array($order->details) ? $order->details : [];
        $requestPayload = is_array($order->request_payload) ? $order->request_payload : [];

        foreach ([
            $details['payment_method'] ?? null,
            $details['payment']['method'] ?? null,
            $requestPayload['payment_method'] ?? null,
            $requestPayload['payment']['method'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return Str::of($candidate)->replace('_', ' ')->title()->toString();
            }
        }

        return null;
    }

    private function humanizeEventLabel(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function supportHistoryDescription(SupportTicketHistory $entry): string
    {
        if ($entry->field === null) {
            return 'Support history entry recorded.';
        }

        if ($entry->old_value !== null || $entry->new_value !== null) {
            return sprintf(
                '%s changed from %s to %s.',
                Str::of($entry->field)->replace('_', ' ')->lower()->toString(),
                $entry->old_value ?: 'empty',
                $entry->new_value ?: 'empty',
            );
        }

        return sprintf(
            '%s was recorded in the support history.',
            Str::of($entry->field)->replace('_', ' ')->lower()->toString(),
        );
    }

    private function orderHistoryDescription(OrderHistory $entry): string
    {
        if ($entry->field === null) {
            return 'Order history entry recorded.';
        }

        if ($entry->old_value !== null || $entry->new_value !== null) {
            return sprintf(
                '%s changed from %s to %s.',
                Str::of($entry->field)->replace('_', ' ')->lower()->toString(),
                $entry->old_value ?: 'empty',
                $entry->new_value ?: 'empty',
            );
        }

        return sprintf(
            '%s was recorded in the order history.',
            Str::of($entry->field)->replace('_', ' ')->lower()->toString(),
        );
    }

    private function financialTimelineLabel(FinancialTransaction $transaction): string
    {
        return match ($transaction->type) {
            FinancialTransaction::TYPE_PAYMENT => 'Payment Captured',
            FinancialTransaction::TYPE_REFUND => (($transaction->metadata['mode'] ?? null) === 'partial') ? 'Partial Refund Applied' : 'Refund Applied',
            FinancialTransaction::TYPE_COMPENSATION => 'Compensation Added',
            FinancialTransaction::TYPE_REVERSAL => 'Refund Reversed',
            FinancialTransaction::TYPE_ADJUSTMENT => 'Financial Adjustment',
            default => $this->humanizeEventLabel($transaction->type),
        };
    }

    private function financialTimelineDescription(FinancialTransaction $transaction): string
    {
        $base = sprintf(
            '%s of %s %s was recorded.',
            Str::of($transaction->type)->replace('_', ' ')->lower()->toString(),
            number_format((float) $transaction->amount, 2, '.', ''),
            $transaction->currency,
        );

        if ($transaction->type === FinancialTransaction::TYPE_COMPENSATION && isset($transaction->metadata['compensation_type'])) {
            $base = sprintf(
                'Compensation was added as %s for %s %s.',
                Str::of((string) $transaction->metadata['compensation_type'])->replace('_', ' ')->lower()->toString(),
                number_format((float) $transaction->amount, 2, '.', ''),
                $transaction->currency,
            );
        }

        if (is_string($transaction->reason) && trim($transaction->reason) !== '') {
            return $base.' Reason: '.trim($transaction->reason);
        }

        return $base;
    }

    private function attachmentUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Persist the uploaded attachment and return metadata for the support message.
     *
     * @return array<string, int|string|null>
     */
    private function storeAttachment(?UploadedFile $attachment): array
    {
        if ($attachment === null || ! $this->supportService->supportsMessageAttachments()) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store('support/attachments', 'public'),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => $this->resolveAttachmentMime($attachment),
            'attachment_size' => $attachment->getSize(),
        ];
    }

    private function resolveAttachmentMime(UploadedFile $attachment): ?string
    {
        $detectedMime = $attachment->getMimeType();

        if (is_string($detectedMime) && $detectedMime !== '' && $detectedMime !== 'application/octet-stream') {
            return $detectedMime;
        }

        $clientMime = $attachment->getClientMimeType();

        return is_string($clientMime) && $clientMime !== '' ? $clientMime : null;
    }

    private function conversationState(?string $lastSenderType): ?string
    {
        return match ($lastSenderType) {
            'user' => 'waiting_for_support',
            'agent' => 'waiting_for_customer',
            default => null,
        };
    }

    /**
     * Apply a simple scalar inbox filter when a value is present.
     */
    private function applyInboxFilter($query, string $column, mixed $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where($column, $value);
    }

    /**
     * Apply inbox search across ticket id, ticket number, customer name, and customer email.
     */
    private function applyInboxSearch($query, ?string $search)
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
     * Apply the selected inbox sort option.
     */
    private function applySort($query, string $sort)
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
     * Build inbox status counters for the current non-status scope.
     *
     * @return array<string, int>
     */
    private function buildCounters($query): array
    {
        return [
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'waiting_customer' => (clone $query)->where('status', 'waiting_customer')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
        ];
    }

    /**
     * Build an empty inbox counter payload.
     *
     * @return array<string, int>
     */
    private function emptyCounters(): array
    {
        return [
            'open' => 0,
            'in_progress' => 0,
            'waiting_customer' => 0,
            'resolved' => 0,
        ];
    }
}