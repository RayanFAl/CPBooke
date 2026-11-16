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

    public const VARIANT_RESET = 'reset';

    public const VARIANT_CHANGE = 'change';

    public function __construct(
        private readonly string $otp,
        private readonly int $expiresInMinutes,
        private readonly string $recipientName,
        private readonly ?string $mailLocale = null,
        private readonly string $variant = self::VARIANT_RESET,
    ) {}

    public function build(): self
    {
        $previousLocale = App::getLocale();
        $locale = NotificationLocales::normalize($this->mailLocale);
        App::setLocale($locale);

        try {
            $copy = $this->copy();

            $mail = $this
                ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
                ->subject($copy['subject'])
                ->view('emails.password-reset-otp', array_merge(BookeMailTheme::viewData($locale), [
                    'subject' => $copy['subject'],
                    'greeting' => __('Hello :name', ['name' => $this->recipientName]),
                    'instruction' => $copy['instruction'],
                    'otp' => $this->otp,
                    'expiresLine' => __('This code expires in :minutes minutes.', ['minutes' => $this->expiresInMinutes]),
                    'ignoreLine' => $copy['ignore'],
                ]));
        } finally {
            App::setLocale($previousLocale);
        }

        $support = trim((string) config('mail.addresses.support', ''));
        if ($support !== '') {
            $mail->replyTo($support, (string) config('mail.names.support', 'Booke Support'));
        }

        return $mail;
    }

    /**
     * @return array{subject: string, instruction: string, ignore: string}
     */
    private function copy(): array
    {
        if ($this->variant === self::VARIANT_CHANGE) {
            return [
                'subject' => __('Password change code'),
                'instruction' => __('Use this code to confirm changing your password:'),
                'ignore' => __('If you did not request a password change, you can ignore this email.'),
            ];
        }

        return [
            'subject' => __('Password reset code'),
            'instruction' => __('Use this code to reset your password:'),
            'ignore' => __('If you did not request a password reset, you can ignore this email.'),
        ];
    }
}
