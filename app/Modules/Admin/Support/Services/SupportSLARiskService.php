<?php

namespace App\Modules\Admin\Support\Services;

use App\Models\SupportMessage;
use App\Models\SupportTicket;

class SupportSLARiskService
{
    private const PRIORITY_SCORES = [
        'low' => 10,
        'medium' => 25,
        'high' => 45,
        'urgent' => 60,
    ];

    /**
     * Resolve a compact SLA risk level for the supplied ticket.
     */
    public function riskLevelFor(SupportTicket $ticket): string
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return 'low';
        }

        $score = $this->priorityScore($ticket)
            + $this->noReplyScore($ticket)
            + $this->deadlineProximityScore($ticket);

        if ($score >= 90) {
            return 'high';
        }

        if ($score >= 50) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Resolve the priority contribution to the SLA risk score.
     */
    private function priorityScore(SupportTicket $ticket): int
    {
        return self::PRIORITY_SCORES[$ticket->priority] ?? self::PRIORITY_SCORES['medium'];
    }

    /**
     * Resolve the no-reply contribution to the SLA risk score.
     */
    private function noReplyScore(SupportTicket $ticket): int
    {
        $latestCustomerMessageAt = $this->latestCustomerMessageAt($ticket);

        if ($latestCustomerMessageAt === null) {
            return 0;
        }

        $latestAgentMessageAt = $this->latestAgentMessageAt($ticket);

        if ($latestAgentMessageAt !== null && $latestAgentMessageAt->greaterThanOrEqualTo($latestCustomerMessageAt)) {
            return 0;
        }

        $minutesWithoutReply = $latestCustomerMessageAt->diffInMinutes(now());

        if ($minutesWithoutReply >= 360) {
            return 35;
        }

        if ($minutesWithoutReply >= 120) {
            return 20;
        }

        if ($minutesWithoutReply >= 60) {
            return 10;
        }

        return 0;
    }

    /**
     * Resolve the deadline proximity contribution to the SLA risk score.
     */
    private function deadlineProximityScore(SupportTicket $ticket): int
    {
        $dueAt = $ticket->first_response_at === null
            ? $ticket->first_response_due_at
            : $ticket->resolution_due_at;

        if ($dueAt === null) {
            return 0;
        }

        if (now()->greaterThan($dueAt)) {
            return 45;
        }

        $referenceStart = $ticket->first_response_at === null
            ? $ticket->created_at
            : $ticket->first_response_at;

        if ($referenceStart === null) {
            return 0;
        }

        $totalWindowMinutes = max($referenceStart->diffInMinutes($dueAt), 1);
        $remainingMinutes = now()->diffInMinutes($dueAt, false);
        $riskThreshold = max((int) ceil($totalWindowMinutes * 0.25), 60);

        if ($remainingMinutes <= 0) {
            return 45;
        }

        if ($remainingMinutes <= $riskThreshold) {
            return 25;
        }

        if ($remainingMinutes <= ($riskThreshold * 2)) {
            return 10;
        }

        return 0;
    }

    /**
     * Resolve the latest customer message timestamp.
     */
    private function latestCustomerMessageAt(SupportTicket $ticket)
    {
        return $this->messages($ticket)
            ->filter(fn (SupportMessage $message): bool => ! $message->user?->isAdminAccount())
            ->sortByDesc(fn (SupportMessage $message) => $message->created_at?->timestamp ?? 0)
            ->first()?->created_at;
    }

    /**
     * Resolve the latest agent message timestamp.
     */
    private function latestAgentMessageAt(SupportTicket $ticket)
    {
        return $this->messages($ticket)
            ->filter(fn (SupportMessage $message): bool => $message->user?->isAdminAccount() === true)
            ->sortByDesc(fn (SupportMessage $message) => $message->created_at?->timestamp ?? 0)
            ->first()?->created_at;
    }

    /**
     * Load support ticket messages with author account types when needed.
     */
    private function messages(SupportTicket $ticket)
    {
        if ($ticket->relationLoaded('messages')) {
            return $ticket->messages->loadMissing('user:id,account_type');
        }

        return $ticket->messages()
            ->with('user:id,account_type')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}