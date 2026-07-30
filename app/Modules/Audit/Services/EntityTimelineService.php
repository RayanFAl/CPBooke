<?php

namespace App\Modules\Audit\Services;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\SupportTicket;
use App\Models\SupportTicketEventLog;
use App\Models\SupportTicketHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EntityTimelineService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forOrder(Order $order): array
    {
        $events = collect();

        $events->push($this->event(
            key: 'order.created',
            label: 'Order Created',
            description: 'Booking '.($order->booking_reference ?: '#'.$order->id).' entered the system.',
            occurredAt: $order->created_at?->toIso8601String(),
            actor: 'System',
            tone: 'slate',
            source: 'order',
        ));

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID || $order->payment_status === Order::PAYMENT_STATUS_PARTIALLY_REFUNDED || $order->payment_status === Order::PAYMENT_STATUS_REFUNDED) {
            $events->push($this->event(
                key: 'order.payment',
                label: 'Payment status: '.str_replace('_', ' ', (string) $order->payment_status),
                description: 'Current payment state on the order.',
                occurredAt: $order->updated_at?->toIso8601String(),
                actor: 'Finance',
                tone: 'emerald',
                source: 'order',
            ));
        }

        if (Schema::hasTable('order_histories')) {
            OrderHistory::query()
                ->with('user:id,name,full_name')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->each(function (OrderHistory $history) use ($events): void {
                    $events->push($this->event(
                        key: 'order.history.'.$history->id,
                        label: $this->humanize(($history->field ?: $history->action) ?? 'updated'),
                        description: trim(($history->old_value ?? '—').' → '.($history->new_value ?? '—')),
                        occurredAt: $history->created_at?->toIso8601String(),
                        actor: $history->user?->full_name ?: $history->user?->name ?: 'System',
                        tone: 'violet',
                        source: 'order_history',
                        meta: [
                            'field' => $history->field,
                            'old_value' => $history->old_value,
                            'new_value' => $history->new_value,
                        ],
                    ));
                });
        }

        if (Schema::hasTable('financial_transactions')) {
            FinancialTransaction::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->each(function (FinancialTransaction $transaction) use ($events): void {
                    $events->push($this->event(
                        key: 'finance.'.$transaction->id,
                        label: $this->humanize($transaction->type).' recorded',
                        description: trim(($transaction->amount ?? '').' '.($transaction->currency ?? '').' via '.($transaction->source ?? 'system')),
                        occurredAt: $transaction->created_at?->toIso8601String(),
                        actor: 'Finance',
                        tone: 'emerald',
                        source: 'financial_transaction',
                    ));
                });
        }

        if (Schema::hasTable('provider_wallet_transactions')) {
            ProviderWalletTransaction::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->each(function (ProviderWalletTransaction $tx) use ($events): void {
                    $events->push($this->event(
                        key: 'wallet.tx.'.$tx->id,
                        label: 'Wallet '.ucfirst((string) $tx->type),
                        description: trim(($tx->amount ?? '').' '.($tx->currency ?? '').' · '.$this->humanize((string) $tx->reference_type)),
                        occurredAt: $tx->created_at?->toIso8601String(),
                        actor: 'Wallet',
                        tone: 'amber',
                        source: 'wallet_transaction',
                    ));
                });
        }

        $this->appendApprovals($events, Approval::ENTITY_ORDER, (int) $order->id);
        $this->appendSettlementItemsForOrder($events, (int) $order->id);
        $this->appendSupportTicketsForOrder($events, (int) $order->id);
        $this->appendAuditLogs($events, AuditLog::ENTITY_ORDER, (int) $order->id);

        return $this->finalize($events);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forSupportTicket(SupportTicket $ticket): array
    {
        $events = collect();

        $events->push($this->event(
            key: 'support.created',
            label: 'Ticket Created',
            description: ($ticket->ticket_number ?: '#'.$ticket->id).' · '.($ticket->subject ?: 'Support ticket'),
            occurredAt: $ticket->created_at?->toIso8601String(),
            actor: 'System',
            tone: 'slate',
            source: 'support',
        ));

        if (Schema::hasTable('support_ticket_histories')) {
            SupportTicketHistory::query()
                ->with('user:id,name,full_name')
                ->where('support_ticket_id', $ticket->id)
                ->orderBy('id')
                ->get()
                ->each(function (SupportTicketHistory $history) use ($events): void {
                    $events->push($this->event(
                        key: 'support.history.'.$history->id,
                        label: $this->humanize($history->action ?: ($history->field ?? 'updated')),
                        description: trim(($history->old_value ?? '—').' → '.($history->new_value ?? '—')),
                        occurredAt: $history->created_at?->toIso8601String(),
                        actor: $history->user?->full_name ?: $history->user?->name ?: 'System',
                        tone: 'violet',
                        source: 'support_history',
                    ));
                });
        }

        if (Schema::hasTable('support_ticket_event_logs')) {
            SupportTicketEventLog::query()
                ->where('ticket_id', $ticket->id)
                ->orderBy('id')
                ->get()
                ->each(function (SupportTicketEventLog $log) use ($events): void {
                    $payload = is_array($log->payload) ? $log->payload : [];
                    $events->push($this->event(
                        key: 'support.event.'.$log->id,
                        label: $this->humanize((string) ($log->event_type ?? 'event')),
                        description: (string) ($payload['message'] ?? $payload['summary'] ?? ''),
                        occurredAt: $log->created_at?->toIso8601String(),
                        actor: 'Support',
                        tone: 'cyan',
                        source: 'support_event',
                    ));
                });
        }

        if ($ticket->order_id) {
            $this->appendApprovals($events, Approval::ENTITY_ORDER, (int) $ticket->order_id);
        }

        $this->appendAuditLogs($events, AuditLog::ENTITY_SUPPORT_TICKET, (int) $ticket->id);

        return $this->finalize($events);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forProviderWallet(ProviderWallet $wallet): array
    {
        $events = collect();

        $events->push($this->event(
            key: 'wallet.created',
            label: 'Wallet Created',
            description: strtoupper((string) $wallet->currency).' · '.($wallet->environment ?: 'production'),
            occurredAt: $wallet->created_at?->toIso8601String(),
            actor: 'System',
            tone: 'slate',
            source: 'wallet',
        ));

        ProviderWalletTransaction::query()
            ->with('creator:id,name,full_name')
            ->where('provider_wallet_id', $wallet->id)
            ->orderBy('id')
            ->limit((int) config('audit.timeline_limit', 100))
            ->get()
            ->each(function (ProviderWalletTransaction $tx) use ($events): void {
                $events->push($this->event(
                    key: 'wallet.tx.'.$tx->id,
                    label: 'Wallet '.ucfirst((string) $tx->type),
                    description: trim(($tx->amount ?? '').' '.($tx->currency ?? '').' → balance '.$tx->balance_after),
                    occurredAt: $tx->created_at?->toIso8601String(),
                    actor: $tx->creator?->full_name ?: $tx->creator?->name ?: 'System',
                    tone: match ($tx->type) {
                        ProviderWalletTransaction::TYPE_DEPOSIT => 'emerald',
                        ProviderWalletTransaction::TYPE_DEBIT => 'amber',
                        default => 'violet',
                    },
                    source: 'wallet_transaction',
                    meta: [
                        'order_id' => $tx->order_id,
                        'reference_type' => $tx->reference_type,
                    ],
                ));
            });

        $this->appendApprovals($events, Approval::ENTITY_WALLET, (int) $wallet->id);
        $this->appendAuditLogs($events, AuditLog::ENTITY_PROVIDER_WALLET, (int) $wallet->id);

        return $this->finalize($events);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forSettlement(Settlement $settlement): array
    {
        $events = collect();

        $events->push($this->event(
            key: 'settlement.created',
            label: 'Settlement Period Created',
            description: ($settlement->period_start?->toDateString() ?? '?').' → '.($settlement->period_end?->toDateString() ?? '?'),
            occurredAt: $settlement->created_at?->toIso8601String(),
            actor: $settlement->creator?->full_name ?: $settlement->creator?->name ?: 'System',
            tone: 'slate',
            source: 'settlement',
        ));

        if ($settlement->compared_at) {
            $events->push($this->event(
                key: 'settlement.compared',
                label: 'Comparison Completed',
                description: 'Matched '.$settlement->matched_count.' · Review '.$settlement->review_count,
                occurredAt: $settlement->compared_at->toIso8601String(),
                actor: 'System',
                tone: 'cyan',
                source: 'settlement',
            ));
        }

        SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', SettlementItem::STATUS_RESOLVED)
            ->orderBy('resolved_at')
            ->limit(40)
            ->get()
            ->each(function (SettlementItem $item) use ($events): void {
                $events->push($this->event(
                    key: 'settlement.item.'.$item->id,
                    label: 'Item Resolved',
                    description: ($item->booking_reference ?: 'Order #'.$item->order_id).' · '.($item->resolution_note ?: ''),
                    occurredAt: $item->resolved_at?->toIso8601String(),
                    actor: 'Settlement',
                    tone: 'violet',
                    source: 'settlement_item',
                ));
            });

        if ($settlement->isClosed()) {
            $events->push($this->event(
                key: 'settlement.closed',
                label: 'Settlement Closed',
                description: 'Period locked for audit.',
                occurredAt: $settlement->closed_at?->toIso8601String(),
                actor: $settlement->closer?->full_name ?: $settlement->closer?->name ?: 'System',
                tone: 'emerald',
                source: 'settlement',
            ));
        }

        $this->appendAuditLogs($events, AuditLog::ENTITY_SETTLEMENT, (int) $settlement->id);

        return $this->finalize($events);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forApproval(Approval $approval): array
    {
        $events = collect();

        $events->push($this->event(
            key: 'approval.requested',
            label: 'Approval Requested',
            description: $this->humanize($approval->type).' · '.($approval->reason ?: ''),
            occurredAt: $approval->created_at?->toIso8601String(),
            actor: $approval->requester?->full_name ?: $approval->requester?->name ?: 'System',
            tone: 'slate',
            source: 'approval',
        ));

        if ($approval->approved_at) {
            $events->push($this->event(
                key: 'approval.approved',
                label: 'Approved',
                description: 'Ready for execution.',
                occurredAt: $approval->approved_at->toIso8601String(),
                actor: $approval->approver?->full_name ?: $approval->approver?->name ?: 'System',
                tone: 'emerald',
                source: 'approval',
            ));
        }

        if ($approval->rejected_at) {
            $events->push($this->event(
                key: 'approval.rejected',
                label: 'Rejected',
                description: (string) ($approval->rejection_reason ?: ''),
                occurredAt: $approval->rejected_at->toIso8601String(),
                actor: $approval->rejector?->full_name ?: $approval->rejector?->name ?: 'System',
                tone: 'rose',
                source: 'approval',
            ));
        }

        if ($approval->executed_at && $approval->status === Approval::STATUS_EXECUTED) {
            $events->push($this->event(
                key: 'approval.executed',
                label: 'Executed',
                description: 'Action completed successfully.',
                occurredAt: $approval->executed_at->toIso8601String(),
                actor: 'System',
                tone: 'emerald',
                source: 'approval',
            ));
        }

        if ($approval->status === Approval::STATUS_FAILED) {
            $events->push($this->event(
                key: 'approval.failed',
                label: 'Execution Failed',
                description: (string) ($approval->execution_error ?: 'Unknown error'),
                occurredAt: $approval->executed_at?->toIso8601String() ?? $approval->updated_at?->toIso8601String(),
                actor: 'System',
                tone: 'rose',
                source: 'approval',
            ));
        }

        $this->appendAuditLogs($events, AuditLog::ENTITY_APPROVAL, (int) $approval->id);

        return $this->finalize($events);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendApprovals(Collection $events, string $entityType, int $entityId): void
    {
        if (! Schema::hasTable('approvals')) {
            return;
        }

        Approval::query()
            ->with(['requester:id,name,full_name', 'approver:id,name,full_name'])
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id')
            ->get()
            ->each(function (Approval $approval) use ($events): void {
                $events->push($this->event(
                    key: 'approval.'.$approval->id.'.requested',
                    label: 'Approval Requested ('.$this->humanize($approval->type).')',
                    description: (string) ($approval->reason ?: ''),
                    occurredAt: $approval->created_at?->toIso8601String(),
                    actor: $approval->requester?->full_name ?: $approval->requester?->name ?: 'System',
                    tone: 'amber',
                    source: 'approval',
                ));

                if ($approval->approved_at) {
                    $events->push($this->event(
                        key: 'approval.'.$approval->id.'.approved',
                        label: 'Approved',
                        description: '#'.$approval->id,
                        occurredAt: $approval->approved_at->toIso8601String(),
                        actor: $approval->approver?->full_name ?: $approval->approver?->name ?: 'System',
                        tone: 'emerald',
                        source: 'approval',
                    ));
                }

                if ($approval->status === Approval::STATUS_EXECUTED && $approval->executed_at) {
                    $events->push($this->event(
                        key: 'approval.'.$approval->id.'.executed',
                        label: 'Executed',
                        description: '#'.$approval->id,
                        occurredAt: $approval->executed_at->toIso8601String(),
                        actor: 'System',
                        tone: 'emerald',
                        source: 'approval',
                    ));
                }

                if ($approval->status === Approval::STATUS_FAILED) {
                    $events->push($this->event(
                        key: 'approval.'.$approval->id.'.failed',
                        label: 'Execution Failed',
                        description: (string) ($approval->execution_error ?: '#'.$approval->id),
                        occurredAt: $approval->executed_at?->toIso8601String() ?? $approval->updated_at?->toIso8601String(),
                        actor: 'System',
                        tone: 'rose',
                        source: 'approval',
                    ));
                }
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendSettlementItemsForOrder(Collection $events, int $orderId): void
    {
        if (! Schema::hasTable('settlement_items')) {
            return;
        }

        SettlementItem::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->each(function (SettlementItem $item) use ($events): void {
                $label = match ($item->status) {
                    SettlementItem::STATUS_MATCHED => 'Settlement Matched',
                    SettlementItem::STATUS_RESOLVED => 'Settlement Variance Resolved',
                    SettlementItem::STATUS_MISSING => 'Settlement Missing on Invoice',
                    SettlementItem::STATUS_EXTRA => 'Settlement Extra on Invoice',
                    SettlementItem::STATUS_DIFFERENT_COST => 'Settlement Cost Mismatch',
                    default => 'Settlement Item Updated',
                };

                $events->push($this->event(
                    key: 'settlement.item.'.$item->id,
                    label: $label,
                    description: 'Settlement #'.$item->settlement_id.' · '.$this->humanize((string) $item->status),
                    occurredAt: $item->resolved_at?->toIso8601String()
                        ?? $item->updated_at?->toIso8601String()
                        ?? $item->created_at?->toIso8601String(),
                    actor: 'Settlement',
                    tone: $item->status === SettlementItem::STATUS_MATCHED ? 'emerald' : 'amber',
                    source: 'settlement_item',
                ));
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendSupportTicketsForOrder(Collection $events, int $orderId): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        SupportTicket::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->each(function (SupportTicket $ticket) use ($events): void {
                $events->push($this->event(
                    key: 'support.ticket.'.$ticket->id,
                    label: 'Support Ticket Opened',
                    description: ($ticket->ticket_number ?: '#'.$ticket->id).' · '.($ticket->subject ?: ''),
                    occurredAt: $ticket->created_at?->toIso8601String(),
                    actor: 'Support',
                    tone: 'cyan',
                    source: 'support',
                ));

                if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                    $events->push($this->event(
                        key: 'support.ticket.'.$ticket->id.'.closed',
                        label: 'Ticket Closed',
                        description: $ticket->ticket_number ?: '#'.$ticket->id,
                        occurredAt: $ticket->updated_at?->toIso8601String(),
                        actor: 'Support',
                        tone: 'emerald',
                        source: 'support',
                    ));
                }
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendAuditLogs(Collection $events, string $entityType, int $entityId): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()
            ->with('actor:id,name,full_name')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id')
            ->limit((int) config('audit.timeline_limit', 100))
            ->get()
            ->each(function (AuditLog $log) use ($events): void {
                $events->push($this->event(
                    key: 'audit.'.$log->id,
                    label: $log->subject,
                    description: $this->humanize($log->action).($log->status === AuditLog::STATUS_FAILED ? ' (failed)' : ''),
                    occurredAt: $log->created_at?->toIso8601String(),
                    actor: $log->actor?->full_name ?: $log->actor?->name ?: 'System',
                    tone: $log->status === AuditLog::STATUS_FAILED ? 'rose' : 'violet',
                    source: 'audit',
                    meta: [
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'status' => $log->status,
                    ],
                ));
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function finalize(Collection $events): array
    {
        return $events
            ->unique('key')
            ->sortByDesc(fn (array $event): int => strtotime((string) ($event['occurred_at'] ?? '')) ?: 0)
            ->values()
            ->take((int) config('audit.timeline_limit', 100))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function event(
        string $key,
        string $label,
        string $description,
        ?string $occurredAt,
        string $actor,
        string $tone,
        string $source,
        array $meta = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'occurred_at' => $occurredAt,
            'actor' => $actor,
            'tone' => $tone,
            'source' => $source,
            'meta' => $meta === [] ? null : $meta,
        ];
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', $value));
    }
}
