<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileOtpNotification extends Notification
{
    public function __construct(
        public readonly string $otp,
        public readonly string $purpose,
        public readonly int $expiresInMinutes,
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
        $subject = match ($this->purpose) {
            'email_change' => __('Confirm your new email'),
            'email_verify' => __('Verify your email'),
            default => __('Verification code'),
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('Hello :name', ['name' => $notifiable->name ?? '']))
            ->line(__('Your verification code is:'))
            ->line("**{$this->otp}**")
            ->line(__('This code expires in :minutes minutes.', ['minutes' => $this->expiresInMinutes]))
            ->line(__('If you did not request this, you can ignore this email.'));
    }
}
