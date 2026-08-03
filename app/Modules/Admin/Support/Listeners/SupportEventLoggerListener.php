<?php

namespace App\Modules\Admin\Support\Listeners;

use App\Models\SupportTicketEventLog;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use Illuminate\Support\Facades\Schema;

class SupportEventLoggerListener
{
    /**
     * Persist a lightweight log for the dispatched support event.
     */
    public function handle(
        SupportTicketCreated|SupportTicketReplied|SupportTicketStatusChanged|SupportTicketAssigned $event,
    ): void {
        if (! Schema::hasTable('support_ticket_event_logs')) {
            return;
        }

        SupportTicketEventLog::query()->create([
            'ticket_id' => $event->ticket->id,
            'event_type' => class_basename($event),
            'payload' => $this->payloadFor($event),
            'created_at' => now(),
        ]);
    }

    /**
    * Build the log payload for the event.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(
        SupportTicketCreated|SupportTicketReplied|SupportTicketStatusChanged|SupportTicketAssigned $event,
    ): array {
        if ($event instanceof SupportTicketCreated) {
            return [
                'ticket_number' => $event->ticket->ticket_number,
                'status' => $event->ticket->status,
            ];
        }

        if ($event instanceof SupportTicketReplied) {
            return [
                'message_id' => $event->message->id,
                'user_id' => $event->message->user_id,
            ];
        }

        if ($event instanceof SupportTicketStatusChanged) {
            return [
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
            ];
        }

        return [
            'old_assigned_to' => $event->oldAssignedTo,
            'new_assigned_to' => $event->newAssignedTo,
        ];
    }
}