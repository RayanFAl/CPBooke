<?php

namespace Database\Factories;

use App\Models\SavedAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedAddress>
 */
class SavedAddressFactory extends Factory
{
    protected $model = SavedAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement(['Home', 'Work', 'Other']),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'latitude' => fake()->latitude(30, 33),
            'longitude' => fake()->longitude(12, 15),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
