<?php

namespace App\Modules\Admin\Support\Events;

use App\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly ?int $oldAssignedTo,
        public readonly ?int $newAssignedTo,
    ) {
    }
}