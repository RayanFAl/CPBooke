<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class AccountDeletionScheduledNotification extends Notification
{
    public function __construct(
        public readonly Carbon $deletionDueAt,
        public readonly int $gracePeriodDays,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->full_name ?? $notifiable->name ?? 'Customer';
        $dueDate = $this->deletionDueAt->toFormattedDateString();

        return (new MailMessage)
            ->subject('Your Booke account is scheduled for deletion')
            ->greeting("Hello {$name},")
            ->line("Your Booke account has been locked and is scheduled for permanent deletion in {$this->gracePeriodDays} days.")
            ->line("Deletion date: {$dueDate}.")
            ->line('Until then you cannot sign in or use the app with this account.')
            ->line('You can cancel the deletion request before that date from the Booke app sign-in screen.');
    }
}
