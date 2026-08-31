<?php

namespace App\Modules\Notifications\Mail;

use App\Support\Mail\BookeMailTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class TemplateNotificationMail extends Mailable
{
    use Queueable;

    public function __construct(
        private readonly ?string $subjectLine,
        private readonly string $bodyText,
        private readonly ?string $mailLocale = null,
    ) {
    }

    public function build(): self
    {
        $subject = $this->subjectLine ?: (string) config('app.name', 'Notification');

        $mail = $this
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject($subject)
            ->view('emails.notification', array_merge(BookeMailTheme::viewData($this->mailLocale), [
                'subject' => $subject,
                'headline' => $subject,
                'bodyHtml' => nl2br(e($this->bodyText)),
            ]));

        $support = trim((string) config('mail.addresses.support', ''));
        if ($support !== '') {
            $mail->replyTo($support, (string) config('mail.from.name'));
        }

        return $mail;
    }
}