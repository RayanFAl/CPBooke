<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    public function __construct(
        public readonly string $otp,
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
        return (new MailMessage)
            ->subject(__('Password reset code'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name ?? '']))
            ->line(__('Use this code to reset your password:'))
            ->line("**{$this->otp}**")
            ->line(__('This code expires in :minutes minutes.', ['minutes' => $this->expiresInMinutes]))
            ->line(__('If you did not request a password reset, you can ignore this email.'));
    }
}
