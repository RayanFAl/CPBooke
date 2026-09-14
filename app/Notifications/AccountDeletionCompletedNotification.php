<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionCompletedNotification extends Notification
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $mode,
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
        $name = $this->displayName !== '' ? $this->displayName : 'Customer';

        return (new MailMessage)
            ->subject('Your Booke account has been deleted')
            ->greeting("Hello {$name},")
            ->line('This confirms that your Booke account has been permanently deleted.')
            ->line(
                $this->mode === 'scheduled'
                    ? 'The scheduled grace period has ended and personal account data that is not required for legal or financial records has been removed.'
                    : 'Personal account data that is not required for legal or financial records has been removed.'
            )
            ->line('If you did not request this, contact Booke support immediately.');
    }
}
