<?php

namespace App\Modules\Support\Services;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;

class SupportAttachmentAccessService
{
    public function canAccess(?User $user, SupportMessage $message): bool
    {
        if ($user === null) {
            return false;
        }

        $message->loadMissing('ticket:id,user_id');

        $ticket = $message->ticket;

        if (! $ticket instanceof SupportTicket) {
            return false;
        }

        if ($user->isCustomerAccount() && (int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        if ($user->isAdminAccount() && $user->can('support.view')) {
            return true;
        }

        return false;
    }

    public function canAccessTicket(?User $user, SupportTicket $ticket): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isCustomerAccount() && (int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        return $user->isAdminAccount() && $user->can('support.view');
    }
}
