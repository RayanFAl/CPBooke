<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['open', 'in_progress', 'waiting_customer', 'resolved']);
        $createdAt = fake()->dateTimeBetween('-21 days', '-2 days');
        $updatedAt = fake()->dateTimeBetween($createdAt, 'now');

        return [
            'ticket_number' => sprintf('SUP-%s-%04d', now()->format('Ymd'), fake()->unique()->numberBetween(1000, 9999)),
            'user_id' => User::factory()->state([
                'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
                'is_admin' => false,
            ]),
            'order_id' => null,
            'category' => fake()->randomElement([
                'booking_change',
                'refund_request',
                'technical_issue',
                'payment_issue',
                'document_request',
            ]),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => $status,
            'assigned_to' => null,
            'subject' => fake()->randomElement([
                'Customer cannot complete booking update',
                'Refund follow-up after partial cancellation',
                'Payment captured but itinerary missing',
                'Traveler requested date amendment',
                'Insurance policy document not received',
            ]),
            'description' => fake()->paragraphs(2, true),
            'first_response_due_at' => fake()->boolean(80) ? fake()->dateTimeBetween($createdAt, '+2 days') : null,
            'resolution_due_at' => fake()->boolean(70) ? fake()->dateTimeBetween($createdAt, '+5 days') : null,
            'resolved_at' => $status === 'resolved' ? fake()->dateTimeBetween($createdAt, 'now') : null,
            'closed_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * Mark the ticket as open.
     */
    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Mark the ticket as in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => 'in_progress',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Mark the ticket as waiting for customer feedback.
     */
    public function waitingCustomer(): static
    {
        return $this->state(fn (): array => [
            'status' => 'waiting_customer',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Mark the ticket as resolved.
     */
    public function resolved(): static
    {
        return $this->state(function (array $attributes): array {
            $resolvedAt = fake()->dateTimeBetween($attributes['created_at'] ?? '-7 days', 'now');

            return [
                'status' => 'resolved',
                'resolved_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ];
        });
    }

    /**
     * Attach a deterministic ticket number prefix for seeded environments.
     */
    public function numbered(string $sequence): static
    {
        return $this->state(fn (): array => [
            'ticket_number' => 'SUP-'.$sequence,
        ]);
    }
}