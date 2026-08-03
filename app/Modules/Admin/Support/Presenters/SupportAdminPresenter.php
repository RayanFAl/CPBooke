<?php

namespace App\Modules\Admin\Support\Presenters;

use App\Models\FinancialTransaction;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketHistory;
use App\Models\SupportTicketResolutionReport;
use App\Models\User;
use App\Modules\Admin\Orders\Services\OrderTicketPayloadBuilder;
use App\Modules\Support\Services\SupportService;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupportAdminPresenter
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly OrderTicketPayloadBuilder $orderTicketPayloadBuilder,
        private readonly SupportAttachmentStorage $attachmentStorage,
    ) {
    }

    public function summary(SupportTicket $ticket): array
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

    public function detail(SupportTicket $ticket, bool $canViewOrderFinancials = false): array
    {
        $lastMessage = $ticket->messages->last();
        $lastSenderType = $lastMessage?->user?->isAdminAccount() ? 'agent' : ($lastMessage ? 'user' : null);
        $resolutionReport = $this->resolutionReportsAvailable() ? $ticket->resolutionReport : null;
        $orderTicket = $ticket->order
            ? $this->orderTicketPayloadBuilder->build($ticket->order, $canViewOrderFinancials)
            : null;

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
            'order_ticket' => $orderTicket,
            'messages' => $ticket->messages
                ->map(fn (SupportMessage $message): array => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'is_internal' => $message->is_internal,
                    'sender_type' => $message->user?->isAdminAccount() ? 'agent' : 'user',
                    'attachment_name' => $message->attachment_name,
                    'attachment_mime' => $message->attachment_mime,
                    'attachment_size' => $message->attachment_size,
                    'attachment_url' => $this->attachmentStorage->temporaryUrl($message),
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

    public function resolutionReportsAvailable(): bool
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

    private function conversationState(?string $lastSenderType): ?string
    {
        return match ($lastSenderType) {
            'user' => 'waiting_for_support',
            'agent' => 'waiting_for_customer',
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function customerNotificationLogs(SupportTicket $ticket): array
    {
        if (! Schema::hasTable('notification_logs')) {
            return [];
        }

        return NotificationLog::query()
            ->where('user_id', $ticket->user_id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (NotificationLog $log): array => $this->notificationLogPayload($log, $ticket))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationLogPayload(NotificationLog $log, SupportTicket $ticket): array
    {
        $response = $log->response_payload ?? [];

        return [
            'id' => $log->id,
            'channel' => $log->channel,
            'template_code' => $log->template_code,
            'notification_type' => $log->notification_type,
            'status' => $log->status,
            'subject' => $log->subject,
            'body' => Str::limit((string) $log->body, 240),
            'retry_count' => $log->retry_count,
            'related_type' => $log->related_type,
            'related_id' => $log->related_id,
            'is_ticket_related' => $log->related_type === 'support_ticket'
                && (int) $log->related_id === (int) $ticket->id,
            'is_order_related' => $ticket->order_id !== null
                && $log->related_type === 'order'
                && (int) $log->related_id === (int) $ticket->order_id,
            'failure_reason' => $response['error'] ?? $response['reason'] ?? null,
            'sent_at' => $log->sent_at?->toDateTimeString(),
            'failed_at' => $log->failed_at?->toDateTimeString(),
            'created_at' => $log->created_at?->toDateTimeString(),
        ];
    }
}
