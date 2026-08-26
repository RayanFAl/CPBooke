<?php

namespace Database\Factories;

use App\Models\LinkedAccountRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedAccountRequest>
 */
class LinkedAccountRequestFactory extends Factory
{
    protected $model = LinkedAccountRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'relationship_type' => 'friend',
            'nickname' => fake()->optional()->firstName(),
            'message' => fake()->optional()->sentence(),
            'status' => LinkedAccountRequest::STATUS_PENDING,
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => LinkedAccountRequest::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => LinkedAccountRequest::STATUS_REJECTED,
            'responded_at' => now(),
        ]);
    }
}
