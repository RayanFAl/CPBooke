<?php

namespace App\Modules\Api\SavedPassengers\Rules;

use App\Models\SavedPassenger;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniquePassportNumberForUser implements ValidationRule
{
    public function __construct(
        private readonly User $user,
        private readonly ?string $ignorePassengerId = null,
    ) {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $duplicateExists = SavedPassenger::query()
            ->where('user_id', $this->user->id)
            ->where('passport_number_hash', SavedPassenger::hashPassportNumber($value))
            ->when($this->ignorePassengerId, fn ($query) => $query->where('id', '!=', $this->ignorePassengerId))
            ->exists();

        if ($duplicateExists) {
            $fail('The passport number has already been saved for this account.');
        }
    }
}
