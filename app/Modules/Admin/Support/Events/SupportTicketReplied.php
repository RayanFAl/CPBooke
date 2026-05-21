<?php

namespace App\Modules\Admin\Support\Events;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketReplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportMessage $message,
    ) {
    }
}