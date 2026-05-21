<?php

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('support.ticket.{ticketId}', function (User $user, int $ticketId): bool {
    $ticket = SupportTicket::query()
        ->select(['id', 'user_id'])
        ->find($ticketId);

    if ($ticket === null) {
        return false;
    }

    if ($user->isCustomerAccount()) {
        return (int) $ticket->user_id === (int) $user->id;
    }

    return Gate::forUser($user)->allows('support.view');
});