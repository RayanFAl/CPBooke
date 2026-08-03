<?php

namespace Database\Factories;

use App\Models\SavedVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedVehicle>
 */
class SavedVehicleFactory extends Factory
{
    protected $model = SavedVehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SavedVehicle::TYPE_COMPULSORY,
            'label' => 'My '.fake()->word(),
            'is_default' => false,
            'beneficiary_name' => fake()->name(),
            'beneficiary_phone' => '+21891'.fake()->numerify('#######'),
            'email' => fake()->safeEmail(),
            'vehicle_type_id' => 1,
            'vehicle_color_id' => 1,
            'vehicle_licensing_authority_id' => 1,
            'vehicle_manufacture_year' => fake()->numberBetween(2005, 2025),
            'vehicle_chassis_number' => strtoupper(fake()->bothify('CHS########')),
            'vehicle_plate_number' => (string) fake()->numerify('#####'),
            'payload' => fake()->optional()->randomFloat(2, 0.5, 5),
            'document_type_id' => null,
            'vehicle_nationality' => null,
            'address' => null,
        ];
    }

    public function orange(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SavedVehicle::TYPE_ORANGE,
            'vehicle_type_id' => null,
            'vehicle_color_id' => null,
            'vehicle_licensing_authority_id' => null,
            'document_type_id' => 14,
            'vehicle_nationality' => 'LBY',
            'address' => 'Tripoli',
            'payload' => null,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
