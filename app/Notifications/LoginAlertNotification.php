<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginAlertNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $deviceName,
        public readonly ?string $ip = null,
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
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting(__('Hello :name', ['name' => $notifiable->name ?? '']))
            ->line($this->body)
            ->line(__('Device: :device', ['device' => $this->deviceName !== '' ? $this->deviceName : 'Unknown']));

        if ($this->ip) {
            $mail->line(__('IP address: :ip', ['ip' => $this->ip]));
        }

        return $mail->line(__('If this was not you, change your password immediately and review active sessions.'));
    }
}
