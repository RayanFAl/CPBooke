<?php

namespace App\Modules\Admin\Support\Services;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SupportAgentScoringService
{
    private const OPEN_STATUSES = ['open', 'in_progress', 'waiting_customer'];

    private const PRIORITY_WEIGHTS = [
        'low' => 1,
        'medium' => 2,
        'high' => 4,
        'urgent' => 6,
    ];

    /**
     * Calculate the current assignment score for the supplied agent.
     */
    public function calculateScore(User $agent): float
    {
        $openTickets = SupportTicket::query()
            ->where('assigned_to', $agent->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get(['priority']);

        $openTicketCount = $openTickets->count();
        $priorityLoad = (float) $openTickets
            ->sum(fn (SupportTicket $ticket): int => self::PRIORITY_WEIGHTS[$ticket->priority] ?? self::PRIORITY_WEIGHTS['medium']);
        $avgResponseMinutes = $this->averageResponseMinutesForAgent($agent->id);

        return ($openTicketCount * 10.0) + ($priorityLoad * 7.5) + ($avgResponseMinutes / 30.0);
    }

    /**
     * Resolve the best available support agent for the given ticket.
     */
    public function getBestAgent(SupportTicket $ticket, ?int $excludeAgentId = null): ?User
    {
        $candidates = $this->candidateAgents($excludeAgentId);

        if ($candidates->isEmpty()) {
            return null;
        }

        $requiredSkill = $this->requiredSkillFor($ticket);
        $skillMatchedCandidates = $requiredSkill === null
            ? $candidates
            : $candidates->filter(fn (User $agent): bool => $this->agentSkills($agent)->contains($requiredSkill))->values();

        $pool = $skillMatchedCandidates->isNotEmpty()
            ? $skillMatchedCandidates
            : $candidates;

        $scoredCandidates = $pool
            ->map(fn (User $agent): array => [
                'agent' => $agent,
                'score' => $this->calculateScore($agent),
                'workload_percentage' => $this->workloadPercentageFor($agent),
            ])
            ->sortBy([
                ['score', 'asc'],
                ['workload_percentage', 'asc'],
                ['agent.id', 'asc'],
            ])
            ->values();

        $availableCandidate = $scoredCandidates
            ->first(fn (array $candidate): bool => $candidate['score'] < $this->overloadScore());

        return ($availableCandidate ?? $scoredCandidates->first())['agent'] ?? null;
    }

    /**
     * Calculate the current workload percentage for the supplied agent.
     */
    public function workloadPercentageFor(User $agent): int
    {
        return (int) min(
            100,
            round(($this->calculateScore($agent) / max($this->overloadScore(), 1.0)) * 100),
        );
    }

    /**
     * Get the configured skill mapping for the supplied agent.
     *
     * @return Collection<int, string>
     */
    private function agentSkills(User $agent): Collection
    {
        $configuredSkills = config('support.auto_assignment.agent_skills', []);

        $skills = data_get($configuredSkills, 'by_id.'.$agent->id)
            ?? data_get($configuredSkills, 'by_email.'.$agent->email)
            ?? data_get($configuredSkills, 'default', ['tech', 'finance', 'general']);

        return collect($skills)
            ->filter(fn (mixed $skill): bool => is_string($skill) && $skill !== '')
            ->values();
    }

    /**
     * Resolve the required skill for the supplied support ticket.
     */
    private function requiredSkillFor(SupportTicket $ticket): ?string
    {
        $skill = config('support.auto_assignment.category_skills.'.$ticket->category);

        return is_string($skill) && $skill !== '' ? $skill : null;
    }

    /**
     * Collect candidate administrative agents for support assignment.
     *
     * @return Collection<int, User>
     */
    private function candidateAgents(?int $excludeAgentId = null): Collection
    {
        return User::query()
            ->where('account_type', User::ACCOUNT_TYPE_ADMIN)
            ->when($excludeAgentId !== null, fn ($query) => $query->where('id', '!=', $excludeAgentId))
            ->select(['id', 'name', 'full_name', 'email'])
            ->get();
    }

    /**
     * Calculate the average first response time for a support agent in minutes.
     */
    private function averageResponseMinutesForAgent(int $agentId): float
    {
        if (! $this->hasFirstResponseAtColumn()) {
            return $this->defaultResponseMinutes();
        }

        $tickets = SupportTicket::query()
            ->where('assigned_to', $agentId)
            ->whereNotNull('first_response_at')
            ->get(['created_at', 'first_response_at']);

        if ($tickets->isEmpty()) {
            return $this->defaultResponseMinutes();
        }

        return (float) $tickets->avg(function (SupportTicket $ticket): float {
            if ($ticket->created_at === null || $ticket->first_response_at === null) {
                return $this->defaultResponseMinutes();
            }

            return (float) $ticket->created_at->diffInMinutes($ticket->first_response_at);
        });
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
     * Resolve the configured overload score threshold.
     */
    private function overloadScore(): float
    {
        return (float) config('support.auto_assignment.overload_score', 140.0);
    }

    /**
     * Resolve the configured default response time in minutes.
     */
    private function defaultResponseMinutes(): float
    {
        return (float) config('support.auto_assignment.default_response_minutes', 240.0);
    }
}