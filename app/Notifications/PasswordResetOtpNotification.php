<?php

namespace App\Notifications;

use App\Mail\PasswordResetOtpMail;
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

    public function toMail(object $notifiable): PasswordResetOtpMail
    {
        return new PasswordResetOtpMail(
            otp: $this->otp,
            expiresInMinutes: $this->expiresInMinutes,
            recipientName: (string) ($notifiable->name ?? ''),
            mailLocale: is_string($notifiable->preferred_locale ?? null) ? $notifiable->preferred_locale : null,
        );
    }
}
