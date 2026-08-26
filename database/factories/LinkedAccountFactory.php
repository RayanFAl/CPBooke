<?php

namespace Database\Factories;

use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedAccount>
 */
class LinkedAccountFactory extends Factory
{
    protected $model = LinkedAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'linked_user_id' => User::factory(),
            'linked_account_request_id' => null,
            'relationship_type' => LinkedAccount::RELATIONSHIP_FRIEND,
            'nickname' => fake()->optional()->firstName(),
            'can_request_payment' => false,
            'can_receive_payment_requests' => true,
            'auto_approve' => false,
            'is_active' => true,
        ];
    }
}
