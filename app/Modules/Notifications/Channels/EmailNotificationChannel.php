<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Mail\TemplateNotificationMail;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Support\Facades\Mail;

class EmailNotificationChannel implements NotificationChannel
{
    public function channel(): string
    {
        return NotificationChannels::EMAIL;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function send(NotificationLog $log, NotificationTemplate $template, User $user, array $variables): array
    {
        if (! is_string($user->email) || trim($user->email) === '') {
            return [
                'provider' => config('mail.default', 'mail'),
                'delivered' => false,
                'reason' => 'missing_email',
            ];
        }

        Mail::to($user->email)->send(new TemplateNotificationMail($log->subject, $log->body));

        return [
            'provider' => config('mail.default', 'mail'),
            'delivered' => true,
            'recipient' => $user->email,
        ];
    }
}