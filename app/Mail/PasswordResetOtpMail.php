<?php

namespace App\Mail;

use App\Modules\Notifications\Support\NotificationLocales;
use App\Support\Mail\BookeMailTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class PasswordResetOtpMail extends Mailable
{
    use Queueable;

    public function __construct(
        private readonly string $otp,
        private readonly int $expiresInMinutes,
        private readonly string $recipientName,
        private readonly ?string $mailLocale = null,
    ) {
    }

    public function build(): self
    {
        $previousLocale = App::getLocale();
        $locale = NotificationLocales::normalize($this->mailLocale);
        App::setLocale($locale);

        try {
            $mail = $this
                ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
                ->subject(__('Password reset code'))
                ->view('emails.password-reset-otp', array_merge(BookeMailTheme::viewData($locale), [
                    'subject' => __('Password reset code'),
                    'greeting' => __('Hello :name', ['name' => $this->recipientName]),
                    'instruction' => __('Use this code to reset your password:'),
                    'otp' => $this->otp,
                    'expiresLine' => __('This code expires in :minutes minutes.', ['minutes' => $this->expiresInMinutes]),
                    'ignoreLine' => __('If you did not request a password reset, you can ignore this email.'),
                ]));
        } finally {
            App::setLocale($previousLocale);
        }

        $support = trim((string) config('mail.addresses.support', ''));
        if ($support !== '') {
            $mail->replyTo($support, (string) config('mail.from.name'));
        }

        return $mail;
    }
}
