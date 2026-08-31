<?php

namespace App\Modules\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Mail\TemplateNotificationMail;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

        try {
            $locale = data_get($variables, 'locale');
            Mail::to($user->email)->send(new TemplateNotificationMail(
                $log->subject,
                $log->body,
                is_string($locale) ? $locale : null,
            ));
        } catch (Throwable $exception) {
            Log::warning('Notification email delivery failed', [
                'user_id' => $user->id,
                'template_code' => $template->code,
                'error' => $exception->getMessage(),
            ]);

            return [
                'provider' => config('mail.default', 'mail'),
                'delivered' => false,
                'reason' => 'smtp_failed',
                'error' => $exception->getMessage(),
                'recipient' => $user->email,
            ];
        }

        return [
            'provider' => config('mail.default', 'mail'),
            'delivered' => true,
            'recipient' => $user->email,
        ];
    }
}
