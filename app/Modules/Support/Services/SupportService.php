<?php

namespace App\Modules\Support\Services;

use App\Models\SupportMessage;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketHistory;
use App\Models\User;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportService
{
    private const REUSABLE_CONVERSATION_STATUSES = [
        'open',
        'in_progress',
        'waiting_customer',
        'resolved',
    ];

    public function __construct(
        private readonly SupportAgentScoringService $supportAgentScoringService,
        private readonly SupportSLARiskService $supportSLARiskService,
    ) {
    }

    private const SLA_WINDOWS = [
        'low' => ['first_response_hours' => 12, 'resolution_hours' => 72],
        'medium' => ['first_response_hours' => 8, 'resolution_hours' => 48],
        'high' => ['first_response_hours' => 4, 'resolution_hours' => 24],
        'urgent' => ['first_response_hours' => 1, 'resolution_hours' => 8],
    ];

    /**
    * Create a support ticket, opening message, and history entries.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createTicket(array $attributes, string $firstMessage, ?int $actorUserId, array $attachment = []): SupportTicket
    {
        return DB::transaction(function () use ($attributes, $firstMessage, $actorUserId, $attachment): SupportTicket {
            $this->assertLinkedOrderOwnership(
                $attributes['user_id'],
                $attributes['order_id'] ?? null,
            );

            $routingTicket = new SupportTicket([
                'category' => $attributes['category'],
                'priority' => $attributes['priority'],
                'status' => $attributes['status'] ?? 'open',
            ]);

            $assignedTo = $attributes['assigned_to']
                ?? $this->supportAgentScoringService->getBestAgent($routingTicket)?->id;

            $ticket = SupportTicket::query()->create([
                'ticket_number' => $this->generateTicketNumber(),
                'user_id' => $attributes['user_id'],
                'order_id' => $attributes['order_id'] ?? null,
                'category' => $attributes['category'],
                'priority' => $attributes['priority'],
                'status' => $attributes['status'] ?? 'open',
                'assigned_to' => $assignedTo,
                'subject' => $attributes['subject'],
                'description' => $firstMessage,
            ]);

            $this->applySlaDeadlines($ticket);

            $this->createMessageRecord($ticket, $ticket->user_id, $firstMessage, false, $attachment);

            $this->recordHistory(
                $ticket,
                $actorUserId,
                'created',
                'status',
                null,
                'open',
            );

            event(new SupportTicketCreated($ticket));

            if ($ticket->assigned_to !== null) {
                $assignee = User::query()->find($ticket->assigned_to);

                $this->recordHistory(
                    $ticket,
                    $actorUserId,
                    'assigned',
                    'assigned_agent_id',
                    null,
                    $assignee?->full_name ?: $assignee?->name ?: (string) $ticket->assigned_to,
                );

                event(new SupportTicketAssigned($ticket, null, $ticket->assigned_to));
            }

            return $ticket;
        });
    }

    /**
     * Send a customer message, reusing the latest matching open ticket when possible.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{ticket: SupportTicket, created: bool}
     */
    public function sendCustomerMessage(array $attributes, string $message, ?int $actorUserId, array $attachment = []): array
    {
        $this->assertLinkedOrderOwnership(
            $attributes['user_id'],
            $attributes['order_id'] ?? null,
        );

        $existingTicket = $this->findOpenConversationTicket(
            (int) $attributes['user_id'],
            $attributes['subject'] ?? null,
            $attributes['order_id'] ?? null,
        );

        if ($existingTicket !== null) {
            $this->addCustomerMessage($existingTicket, $message, $actorUserId, $attachment);

            return [
                'ticket' => $existingTicket,
                'created' => false,
            ];
        }

        return [
            'ticket' => $this->createTicket($attributes, $message, $actorUserId, $attachment),
            'created' => true,
        ];
    }

    /**
     * Paginate the authenticated customer's support tickets.
     */
    public function getUserTickets(User $customer, int $perPage = 10): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->with([
                'assignee:id,name,full_name,email',
                'order:id,customer_id,booking_reference,external_booking_id,status',
                'latestMessage.user:id,name,full_name,email,account_type',
            ])
            ->whereBelongsTo($customer, 'user')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Paginate chat ticket summaries for the authenticated customer.
     */
    public function getChatTicketsForCustomer(User $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getUserTickets($customer, $perPage);
    }

    /**
     * Load a single support ticket for the authenticated customer.
     */
    public function getTicketDetails(User $customer, int $ticketId): SupportTicket
    {
        return $this->resolveCustomerTicket($customer, $ticketId, [
            'user:id,name,full_name,email,phone,country,created_at',
            'assignee:id,name,full_name,email',
            'order:'.$this->orderRelationSelectColumns(),
            'latestMessage.user:id,name,full_name,email,account_type',
            'messages.user:id,name,full_name,email,account_type',
        ]);
    }

    /**
     * Load a chat ticket summary for the authenticated customer.
     */
    public function getChatTicketForCustomer(User $customer, int $ticketId): SupportTicket
    {
        return $this->resolveCustomerTicket($customer, $ticketId, [
            'assignee:id,name,full_name,email',
            'latestMessage.user:id,name,full_name,email,account_type',
            'messages.user:id,name,full_name,email,account_type',
        ]);
    }

    /**
     * Paginate customer-facing chat messages for a support ticket.
     */
    public function getChatMessagesForCustomer(User $customer, int $ticketId, int $perPage = 20): LengthAwarePaginator
    {
        $ticket = $this->resolveCustomerTicket($customer, $ticketId);

        return SupportMessage::query()
            ->with(['user:id,name,full_name,email,account_type'])
            ->where('support_ticket_id', $ticket->id)
            ->where('is_internal', false)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * Load the latest matching open conversation for the authenticated customer.
     */
    public function getCurrentConversation(User $customer, ?string $subject = null, ?int $orderId = null): ?SupportTicket
    {
        $ticket = $this->findOpenConversationTicket($customer->id, $subject, $orderId);

        if ($ticket === null) {
            return null;
        }

        return $this->getTicketDetails($customer, $ticket->id);
    }

    /**
     * Add a customer message to an existing support ticket.
     */
    public function addCustomerMessage(SupportTicket $ticket, string $message, ?int $actorUserId, array $attachment = []): void
    {
        DB::transaction(function () use ($ticket, $message, $actorUserId, $attachment): void {
            $supportMessage = $this->createMessageRecord($ticket, $actorUserId, $message, false, $attachment);

            $oldStatus = $ticket->status;

            $ticket->touch();

            if (in_array($oldStatus, ['waiting_customer', 'resolved'], true)) {
                $ticket->forceFill([
                    'status' => 'open',
                ])->save();

                $this->applyResolutionStatus($ticket, 'open');

                $this->recordHistory(
                    $ticket,
                    $actorUserId,
                    'status_changed',
                    'status',
                    $oldStatus,
                    'open',
                );

                event(new SupportTicketStatusChanged($ticket, $oldStatus, 'open'));
            }

            $this->recordHistory(
                $ticket,
                $actorUserId,
                'replied',
                'message',
                null,
                'customer_reply',
            );

            event(new SupportTicketReplied($ticket, $supportMessage));
        });

        $this->maybeReassignHighRiskTicket($ticket, $actorUserId);
    }

    /**
     * Add an agent reply, update first response tracking, and emit the reply event.
     */
    public function replyToTicket(SupportTicket $ticket, string $message, ?int $actorUserId, array $attachment = []): void
    {
        DB::transaction(function () use ($ticket, $message, $actorUserId, $attachment): void {
            $supportMessage = $this->createMessageRecord($ticket, $actorUserId, $message, false, $attachment);

            $this->registerFirstResponse($ticket);

            $ticket->touch();

            $this->recordHistory(
                $ticket,
                $actorUserId,
                'replied',
                'message',
                null,
                'agent_reply',
            );

            event(new SupportTicketReplied($ticket, $supportMessage));
        });

        $this->maybeReassignHighRiskTicket($ticket, $actorUserId);
    }

    /**
     * Update the ticket status and emit a status-changed event when needed.
     */
    public function updateTicketStatus(SupportTicket $ticket, string $newStatus, ?int $actorUserId): void
    {
        DB::transaction(function () use ($ticket, $newStatus, $actorUserId): void {
            $oldStatus = $ticket->status;

            if ($newStatus === $oldStatus) {
                return;
            }

            $ticket->forceFill([
                'status' => $newStatus,
            ])->save();

            $this->applyResolutionStatus($ticket, $newStatus);

            $this->recordHistory(
                $ticket,
                $actorUserId,
                'status_changed',
                'status',
                $oldStatus,
                $newStatus,
            );

            event(new SupportTicketStatusChanged($ticket, $oldStatus, $newStatus));
        });

        $this->maybeReassignHighRiskTicket($ticket, $actorUserId);
    }

    /**
     * Update the assigned support agent and emit an assignment event when needed.
     */
    public function assignTicket(SupportTicket $ticket, ?int $newAssignedTo, ?int $actorUserId): void
    {
        DB::transaction(function () use ($ticket, $newAssignedTo, $actorUserId): void {
            $oldAssignedTo = $ticket->assigned_to;

            if ($oldAssignedTo === $newAssignedTo) {
                return;
            }

            $oldAssignee = $oldAssignedTo ? User::query()->find($oldAssignedTo) : null;
            $newAssignee = $newAssignedTo ? User::query()->find($newAssignedTo) : null;

            $ticket->forceFill([
                'assigned_to' => $newAssignedTo,
            ])->save();

            $this->recordHistory(
                $ticket,
                $actorUserId,
                'assigned',
                'assigned_agent_id',
                $oldAssignee?->full_name ?: $oldAssignee?->name,
                $newAssignee?->full_name ?: $newAssignee?->name,
            );

            event(new SupportTicketAssigned($ticket, $oldAssignedTo, $newAssignedTo));
        });
    }

    /**
     * Persist an internal support message for operational events.
     */
    public function addInternalMessage(SupportTicket $ticket, ?int $actorUserId, string $message): SupportMessage
    {
        return DB::transaction(function () use ($ticket, $actorUserId, $message): SupportMessage {
            $supportMessage = $this->createMessageRecord($ticket, $actorUserId, $message, true);

            $ticket->touch();

            return $supportMessage;
        });
    }

    /**
     * Mark customer-visible agent messages as seen.
     *
     * @param  array<int, int|string>|null  $messageIds
     */
    public function markMessagesSeenForCustomer(SupportTicket $ticket, User $customer, ?array $messageIds = null): int
    {
        if ((int) $ticket->user_id !== (int) $customer->id) {
            throw new AuthorizationException('You are not allowed to access this support ticket.');
        }

        if (! $this->supportsChatMessageAttributes()) {
            return 0;
        }

        $query = SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_internal', false)
            ->where('sender_type', 'agent')
            ->whereNull('seen_at');

        if (is_array($messageIds) && $messageIds !== []) {
            $query->whereIn('id', array_map('intval', $messageIds));
        }

        return $query->update(['seen_at' => now()]);
    }

    /**
     * Store ephemeral typing metadata for the chat transport layer.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function storeTypingState(SupportTicket $ticket, User $actor, bool $typing = true, array $metadata = []): array
    {
        if ((int) $ticket->user_id !== (int) $actor->id && $actor->isCustomerAccount()) {
            throw new AuthorizationException('You are not allowed to access this support ticket.');
        }

        $payload = [
            'ticket_id' => $ticket->id,
            'typing' => $typing,
            'sender_type' => $actor->isAdminAccount() ? 'agent' : 'customer',
            'sender' => [
                'id' => $actor->id,
                'name' => $actor->full_name ?: $actor->name,
                'avatar' => null,
            ],
            'metadata' => $metadata,
            'created_at' => now()->toDateTimeString(),
        ];

        $cacheKey = $this->typingCacheKey($ticket->id, $actor->id);

        if ($typing) {
            Cache::put($cacheKey, $payload, now()->addSeconds(8));
        } else {
            Cache::forget($cacheKey);
        }

        return $payload;
    }

    public function isAgentTyping(SupportTicket $ticket): bool
    {
        if ($ticket->assigned_to === null) {
            return false;
        }

        return Cache::has($this->typingCacheKey($ticket->id, (int) $ticket->assigned_to));
    }

    public function typingCacheKey(int $ticketId, int $userId): string
    {
        return sprintf('support:ticket:%d:typing:%d', $ticketId, $userId);
    }

    /**
     * Persist a support history entry for operational actions initiated inside the ticket workspace.
     */
    public function recordOperationalHistory(
        SupportTicket $ticket,
        ?int $userId,
        string $action,
        ?string $field,
        ?string $oldValue,
        ?string $newValue,
    ): void {
        $this->recordHistory($ticket, $userId, $action, $field, $oldValue, $newValue);
    }

    /**
     * Apply SLA due timestamps when a ticket is first created.
     */
    public function applySlaDeadlines(SupportTicket $ticket): void
    {
        $referenceTime = $ticket->created_at ?? now();
        $windows = self::SLA_WINDOWS[$ticket->priority] ?? self::SLA_WINDOWS['medium'];

        $ticket->forceFill([
            'first_response_due_at' => $referenceTime->copy()->addHours($windows['first_response_hours']),
            'resolution_due_at' => $referenceTime->copy()->addHours($windows['resolution_hours']),
        ])->save();
    }

    /**
     * Record the first response timestamp once.
     */
    public function registerFirstResponse(SupportTicket $ticket): void
    {
        if (! $this->hasFirstResponseAtColumn()) {
            return;
        }

        if ($ticket->getAttribute('first_response_at') !== null) {
            return;
        }

        $ticket->forceFill([
            'first_response_at' => now(),
        ])->save();
    }

    /**
     * Apply resolved timestamp semantics for status changes.
     */
    public function applyResolutionStatus(SupportTicket $ticket, string $newStatus): void
    {
        $attributes = [];

        if ($newStatus === 'resolved') {
            $attributes['resolved_at'] = now();
            $attributes['closed_at'] = null;
        } elseif ($newStatus === 'closed') {
            $attributes['closed_at'] = now();
            $attributes['resolved_at'] = $ticket->resolved_at ?? now();
        } else {
            $attributes['resolved_at'] = null;
            $attributes['closed_at'] = null;
        }

        $ticket->forceFill($attributes)->save();
    }

    /**
     * Resolve a compact SLA status for inbox badges.
     */
    public function slaStatusFor(SupportTicket $ticket): string
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return $this->completedSlaStatus($ticket);
        }

        if (! $this->hasFirstResponseAtColumn() || $ticket->getAttribute('first_response_at') === null) {
            return $this->timeBasedStatus(
                $ticket->created_at,
                $ticket->first_response_due_at,
            );
        }

        return $this->timeBasedStatus(
            $ticket->created_at,
            $ticket->resolution_due_at,
        );
    }

    /**
     * Resolve a compact SLA risk level for support routing and visibility.
     */
    public function slaRiskFor(SupportTicket $ticket): string
    {
        return $this->supportSLARiskService->riskLevelFor($ticket);
    }

    /**
     * Resolve the current workload percentage for the supplied support agent.
     */
    public function agentWorkloadPercentageFor(?User $agent): ?int
    {
        if ($agent === null) {
            return null;
        }

        return $this->supportAgentScoringService->workloadPercentageFor($agent);
    }

    /**
     * Determine whether support message attachment metadata columns exist.
     */
    public function supportsMessageAttachments(): bool
    {
        if (! Schema::hasTable('support_messages')) {
            return false;
        }

        foreach (['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'] as $column) {
            if (! Schema::hasColumn('support_messages', $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether chat-grade support message columns exist.
     */
    public function supportsChatMessageAttributes(): bool
    {
        if (! Schema::hasTable('support_messages')) {
            return false;
        }

        foreach (['sender_type', 'message_type', 'metadata', 'reply_to_id', 'delivered_at', 'seen_at'] as $column) {
            if (! Schema::hasColumn('support_messages', $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve SLA status for completed tickets.
     */
    private function completedSlaStatus(SupportTicket $ticket): string
    {
        if ($ticket->resolution_due_at === null || $ticket->resolved_at === null) {
            return 'on_track';
        }

        return $ticket->resolved_at->greaterThan($ticket->resolution_due_at)
            ? 'overdue'
            : 'on_track';
    }

    /**
     * Resolve time-based SLA status using simple overdue and risk thresholds.
     */
    private function timeBasedStatus(?CarbonInterface $startAt, ?CarbonInterface $dueAt): string
    {
        if ($startAt === null || $dueAt === null) {
            return 'on_track';
        }

        $now = now();

        if ($now->greaterThan($dueAt)) {
            return 'overdue';
        }

        $totalWindowMinutes = max($startAt->diffInMinutes($dueAt, false), 1);
        $remainingMinutes = $now->diffInMinutes($dueAt, false);
        $riskThreshold = max((int) ceil($totalWindowMinutes * 0.25), 60);

        return $remainingMinutes <= $riskThreshold
            ? 'at_risk'
            : 'on_track';
    }

    /**
     * Determine whether first response tracking exists in the current schema.
     */
    private function hasFirstResponseAtColumn(): bool
    {
        return Schema::hasTable('support_tickets')
            && Schema::hasColumn('support_tickets', 'first_response_at');
    }

    /**
     * Locate the latest open ticket matching the same order first, then the same subject.
     */
    private function findOpenConversationTicket(int $userId, ?string $subject = null, ?int $orderId = null): ?SupportTicket
    {
        $baseQuery = SupportTicket::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::REUSABLE_CONVERSATION_STATUSES)
            ->latest('updated_at')
            ->latest('id');

        if ($orderId !== null) {
            $orderMatch = (clone $baseQuery)
                ->where('order_id', $orderId)
                ->first();

            if ($orderMatch !== null) {
                return $orderMatch;
            }
        }

        $normalizedSubject = trim((string) $subject);

        if ($normalizedSubject === '') {
            return null;
        }

        return (clone $baseQuery)
            ->whereRaw('LOWER(subject) = ?', [mb_strtolower($normalizedSubject)])
            ->first();
    }

    /**
     * Persist a support message with optional attachment metadata.
     *
     * @param  array<string, mixed>  $attachment
     */
    private function createMessageRecord(
        SupportTicket $ticket,
        ?int $userId,
        string $message,
        bool $isInternal = false,
        array $attachment = [],
    ): SupportMessage {
        $attributes = [
            'support_ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $message,
            'is_internal' => $isInternal,
        ];

        if ($this->supportsChatMessageAttributes()) {
            $attributes['sender_type'] = $this->resolveMessageSenderType($userId, $isInternal);
            $attributes['message_type'] = $this->resolveMessageType($isInternal, $attachment);
            $attributes['metadata'] = $this->normalizeMessageMetadata(Arr::get($attachment, 'metadata'));
            $attributes['reply_to_id'] = Arr::get($attachment, 'reply_to_id');
            $attributes['delivered_at'] = now();
            $attributes['seen_at'] = null;
        }

        if ($this->supportsMessageAttachments()) {
            $attributes['attachment_path'] = $attachment['attachment_path'] ?? null;
            $attributes['attachment_name'] = $attachment['attachment_name'] ?? null;
            $attributes['attachment_mime'] = $attachment['attachment_mime'] ?? null;
            $attributes['attachment_size'] = $attachment['attachment_size'] ?? null;
        }

        return SupportMessage::query()->create($attributes);
    }

    /**
     * Attempt a smart reassignment for high-risk tickets when enabled.
     */
    private function maybeReassignHighRiskTicket(SupportTicket $ticket, ?int $actorUserId): void
    {
        if (! config('support.smart_reassignment.enabled', false)) {
            return;
        }

        $ticket->loadMissing('assignee:id,name,full_name,email');

        if ($this->slaRiskFor($ticket) !== 'high') {
            return;
        }

        $currentAssignee = $ticket->assignee;
        $replacement = $this->supportAgentScoringService->getBestAgent($ticket, $ticket->assigned_to);

        if ($replacement === null) {
            return;
        }

        $currentWorkload = $currentAssignee !== null
            ? $this->agentWorkloadPercentageFor($currentAssignee)
            : 101;
        $replacementWorkload = $this->agentWorkloadPercentageFor($replacement) ?? 0;
        $minimumImprovement = (int) config('support.smart_reassignment.minimum_workload_improvement', 5);

        if ($ticket->assigned_to !== null && $replacement->id === $ticket->assigned_to) {
            return;
        }

        if ($ticket->assigned_to !== null && ($currentWorkload - $replacementWorkload) < $minimumImprovement) {
            return;
        }

        $this->assignTicket($ticket, $replacement->id, $actorUserId);
    }

    /**
     * Ensure a linked order belongs to the same customer as the ticket.
     */
    private function assertLinkedOrderOwnership(int $userId, ?int $orderId): void
    {
        if ($orderId === null) {
            return;
        }

        $order = Order::query()->find($orderId);

        if ($order === null) {
            throw (new ModelNotFoundException())->setModel(Order::class, [$orderId]);
        }

        if ((int) $order->customer_id !== $userId) {
            throw new AuthorizationException('You are not allowed to link this order to the support ticket.');
        }
    }

    /**
     * @param  array<int, string>  $relations
     */
    private function resolveCustomerTicket(User $customer, int $ticketId, array $relations = []): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->with($relations)
            ->find($ticketId);

        if ($ticket === null) {
            throw (new ModelNotFoundException())->setModel(SupportTicket::class, [$ticketId]);
        }

        if ((int) $ticket->user_id !== (int) $customer->id) {
            throw new AuthorizationException('You are not allowed to access this support ticket.');
        }

        return $ticket;
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
            'created_at',
        ];

        if (Schema::hasColumn('orders', 'payment_status')) {
            $columns[] = 'payment_status';
        }

        return implode(',', $columns);
    }

    /**
     * Persist a support ticket history entry.
     */
    private function recordHistory(
        SupportTicket $ticket,
        ?int $userId,
        string $action,
        ?string $field,
        ?string $oldValue,
        ?string $newValue,
    ): void {
        SupportTicketHistory::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $userId,
            'action' => $action,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_at' => now(),
        ]);
    }

    /**
     * Generate a unique support ticket number.
     */
    private function generateTicketNumber(): string
    {
        do {
            $candidate = sprintf('SUP-%s-%04d', now()->format('Ymd'), random_int(1000, 9999));
        } while (SupportTicket::query()->where('ticket_number', $candidate)->exists());

        return $candidate;
    }

    private function resolveMessageSenderType(?int $userId, bool $isInternal): string
    {
        if ($isInternal) {
            return 'system';
        }

        if ($userId === null) {
            return 'system';
        }

        $user = User::query()->find($userId);

        if ($user?->isAdminAccount()) {
            return 'agent';
        }

        return 'customer';
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function resolveMessageType(bool $isInternal, array $attachment): string
    {
        if ($isInternal) {
            return 'system';
        }

        $mime = (string) ($attachment['attachment_mime'] ?? '');

        if ($mime !== '') {
            if (str_starts_with($mime, 'image/')) {
                return 'image';
            }

            if (str_starts_with($mime, 'video/')) {
                return 'video';
            }

            return 'file';
        }

        return 'text';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMessageMetadata(mixed $metadata): ?array
    {
        return is_array($metadata) && $metadata !== [] ? $metadata : null;
    }
}