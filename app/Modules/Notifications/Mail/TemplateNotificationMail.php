<?php

namespace App\Modules\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class TemplateNotificationMail extends Mailable
{
    use Queueable;

    public function __construct(
        private readonly ?string $subjectLine,
        private readonly string $bodyText,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine ?: config('app.name', 'Notification'))
            ->html('<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.7;color:#0f172a;">'.nl2br(e($this->bodyText)).'</div>');
    }
}