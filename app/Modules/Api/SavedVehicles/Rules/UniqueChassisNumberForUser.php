<?php

namespace App\Modules\Api\SavedVehicles\Rules;

use App\Models\SavedVehicle;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueChassisNumberForUser implements ValidationRule
{
    public function __construct(
        private readonly User $user,
        private readonly ?string $ignoreVehicleId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $duplicateExists = SavedVehicle::query()
            ->where('user_id', $this->user->id)
            ->where('vehicle_chassis_number_hash', SavedVehicle::hashChassis($value))
            ->when($this->ignoreVehicleId, fn ($query) => $query->where('id', '!=', $this->ignoreVehicleId))
            ->exists();

        if ($duplicateExists) {
            $fail('The chassis number has already been saved for this account.');
        }
    }
}
