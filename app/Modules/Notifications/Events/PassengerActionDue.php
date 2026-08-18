<?php

namespace App\Modules\Notifications\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PassengerActionDue
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly array $payload = [],
        public readonly ?string $relatedType = null,
        public readonly int|string|null $relatedId = null,
        public readonly array $channels = [],
    ) {}
}
