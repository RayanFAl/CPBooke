<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketHistory>
 */
class SupportTicketHistoryFactory extends Factory
{
    protected $model = SupportTicketHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'user_id' => User::factory(),
            'action' => 'created',
            'field' => 'status',
            'old_value' => null,
            'new_value' => 'open',
            'created_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }

    /**
     * Mark the history row as ticket creation.
     */
    public function created(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user?->id,
            'action' => 'created',
            'field' => 'status',
            'old_value' => null,
            'new_value' => 'open',
        ]);
    }

    /**
     * Mark the history row as ticket assignment.
     */
    public function assigned(?User $agent = null, ?string $assigneeLabel = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $agent?->id,
            'action' => 'assigned',
            'field' => 'assigned_to',
            'old_value' => null,
            'new_value' => $assigneeLabel,
        ]);
    }

    /**
     * Mark the history row as a status change.
     */
    public function statusChanged(?User $agent = null, string $from = 'open', string $to = 'in_progress'): static
    {
        return $this->state(fn (): array => [
            'user_id' => $agent?->id,
            'action' => 'status_changed',
            'field' => 'status',
            'old_value' => $from,
            'new_value' => $to,
        ]);
    }

    /**
     * Mark the history row as an agent reply.
     */
    public function replied(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user?->id,
            'action' => 'replied',
            'field' => 'message',
            'old_value' => null,
            'new_value' => 'agent_response',
        ]);
    }
}