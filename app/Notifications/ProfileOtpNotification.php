<?php

namespace App\Notifications;

use App\Modules\Api\User\Services\ProfileOtpService;
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
            ProfileOtpService::PURPOSE_EMAIL_CHANGE => __('Confirm your new email'),
            ProfileOtpService::PURPOSE_EMAIL_VERIFY => __('Verify your email'),
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
