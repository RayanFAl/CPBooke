<?php

namespace Database\Factories;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

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
            'message' => fake()->realTextBetween(80, 180),
            'is_internal' => false,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
            'created_at' => fake()->dateTimeBetween('-14 days', 'now'),
            'updated_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }

    /**
     * Create a customer-authored message.
     */
    public function fromUser(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user?->id ?? User::factory()->state([
                'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
                'is_admin' => false,
            ]),
            'is_internal' => false,
        ]);
    }

    /**
     * Create an agent-authored message.
     */
    public function fromAgent(?User $agent = null, bool $internal = false): static
    {
        return $this->state(fn (): array => [
            'user_id' => $agent?->id ?? User::factory()->state([
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'is_admin' => true,
            ]),
            'is_internal' => $internal,
        ]);
    }
}