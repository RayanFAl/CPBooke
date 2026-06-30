<?php

namespace Database\Factories;

use App\Models\SavedPassenger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedPassenger>
 */
class SavedPassengerFactory extends Factory
{
    protected $model = SavedPassenger::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SavedPassenger::TYPE_ADT,
            'title' => fake()->randomElement(['Mr', 'Mrs', 'Ms', 'Miss']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->date('Y-m-d', '-25 years'),
            'gender' => fake()->randomElement(SavedPassenger::genders()),
            'nationality' => 'SAU',
            'country_of_residence' => 'SAU',
            'document_type' => SavedPassenger::DOCUMENT_PASSPORT,
            'passport_number' => strtoupper(fake()->bothify('??#######')),
            'passport_issue_country' => 'SAU',
            'passport_issue_date' => fake()->date('Y-m-d', '-5 years'),
            'passport_expiry' => fake()->date('Y-m-d', '+5 years'),
            'email' => fake()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'seat_preference' => fake()->optional()->randomElement(['window', 'aisle', 'middle']),
            'meal_preference' => fake()->optional()->randomElement(['standard', 'vegetarian', 'halal']),
            'is_default' => false,
        ];
    }

    /**
     * Mark the passenger as the default profile for the user.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
