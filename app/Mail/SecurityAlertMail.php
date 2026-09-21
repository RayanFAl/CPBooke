<?php

namespace App\Mail;

use App\Modules\Notifications\Support\NotificationLocales;
use App\Support\Mail\BookeMailTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use InvalidArgumentException;

class SecurityAlertMail extends Mailable
{
    use Queueable;

    public const VARIANT_LOGIN = 'login';

    public const VARIANT_PASSWORD_CHANGED = 'password_changed';

    /**
     * @param  array{device_name?: string|null, ip?: string|null, occurred_at?: string|null, timezone_label?: string|null, location?: string|null, cta_url?: string|null}  $meta
     */
    public function __construct(
        private readonly string $variant,
        private readonly string $recipientName,
        private readonly ?string $mailLocale = null,
        private readonly array $meta = [],
    ) {
        if (! in_array($variant, [self::VARIANT_LOGIN, self::VARIANT_PASSWORD_CHANGED], true)) {
            throw new InvalidArgumentException("Unsupported security mail variant [{$variant}].");
        }
    }

    public function build(): self
    {
        $locale = NotificationLocales::normalize($this->mailLocale);
        $copy = $this->copy($locale);
        $subject = $copy['subject'];
        $ctaUrl = trim((string) ($this->meta['cta_url'] ?? '')) ?: rtrim((string) config('app.url'), '/');
        $rtl = BookeMailTheme::isRtl($locale);

        $mail = $this
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject($subject)
            ->view('emails.security', array_merge(BookeMailTheme::viewData($locale), [
                'subject' => $subject,
                'eyebrow' => $copy['eyebrow'],
                'headline' => $copy['headline'],
                'intro' => $copy['intro'],
                'details' => $this->details($locale),
                'warningTitle' => $copy['warningTitle'],
                'warningBody' => $copy['warningBody'],
                'ctaLabel' => $copy['ctaLabel'],
                'ctaUrl' => $ctaUrl,
                'footerHelpTitle' => $rtl
                    ? 'هل تحتاج إلى مساعدة؟'
                    : 'Need help?',
                'footerHelpBody' => $rtl
                    ? 'تواصل مع فريق الدعم عبر'
                    : 'Contact our support team at',
            ]));

        $support = trim((string) config('mail.addresses.support', ''));
        if ($support !== '') {
            $mail->replyTo($support, (string) config('mail.names.support', 'Booke Support'));
        }

        return $mail;
    }

    /**
     * @return array{subject: string, eyebrow: string, headline: string, intro: string, warningTitle: string, warningBody: string, ctaLabel: string}
     */
    private function copy(string $locale): array
    {
        $name = trim($this->recipientName) !== '' ? $this->recipientName : ($locale === NotificationLocales::AR ? 'عميلنا' : 'there');

        if ($this->variant === self::VARIANT_PASSWORD_CHANGED) {
            return $locale === NotificationLocales::AR ? [
                'subject' => 'تم تغيير كلمة المرور',
                'eyebrow' => 'أمان الحساب',
                'headline' => 'تم تغيير كلمة مرور حسابك',
                'intro' => "مرحباً {$name}، تم تحديث كلمة مرور حسابك في Booke بنجاح.",
                'warningTitle' => 'إذا لم تكن أنت من قام بهذا التغيير،',
                'warningBody' => 'قم بتغيير كلمة المرور وراجع الأجهزة المتصلة بحسابك.',
                'ctaLabel' => 'تأمين حسابي',
            ] : [
                'subject' => 'Your password was changed',
                'eyebrow' => 'Account security',
                'headline' => 'Your account password was changed',
                'intro' => "Hi {$name}, the password on your Booke account was updated successfully.",
                'warningTitle' => "If you didn't make this change,",
                'warningBody' => 'reset your password and review the devices connected to your account.',
                'ctaLabel' => 'Secure my account',
            ];
        }

        return $locale === NotificationLocales::AR ? [
            'subject' => 'تسجيل دخول جديد إلى حسابك',
            'eyebrow' => 'أمان الحساب',
            'headline' => 'تم تسجيل دخول جديد إلى حسابك',
            'intro' => "مرحباً {$name}، سجّل أحدهم الدخول إلى حسابك. إذا كنت أنت، لا حاجة لأي إجراء.",
            'warningTitle' => 'إذا لم تكن أنت من قام بتسجيل الدخول،',
            'warningBody' => 'قم بتغيير كلمة المرور وراجع الأجهزة المتصلة بحسابك.',
            'ctaLabel' => 'مراجعة نشاط الحساب',
        ] : [
            'subject' => 'New login to your account',
            'eyebrow' => 'Account security',
            'headline' => 'A new sign-in to your account',
            'intro' => "Hi {$name}, someone signed in to your Booke account. If this was you, no action is needed.",
            'warningTitle' => "If you didn't sign in,",
            'warningBody' => 'change your password and review the devices connected to your account.',
            'ctaLabel' => 'Review account activity',
        ];
    }

    /**
     * @return list<array{label: string, value: string, mono?: bool, hint?: string}>
     */
    private function details(string $locale): array
    {
        $ar = $locale === NotificationLocales::AR;
        $rows = [];

        $device = trim((string) ($this->meta['device_name'] ?? ''));
        if ($device !== '') {
            $rows[] = [
                'label' => $ar ? 'الجهاز' : 'Device',
                'value' => $device,
            ];
        }

        $ip = trim((string) ($this->meta['ip'] ?? ''));
        if ($ip !== '') {
            $rows[] = [
                'label' => $ar ? 'عنوان IP' : 'IP address',
                'value' => $ip,
                'mono' => true,
            ];
        }

        $location = trim((string) ($this->meta['location'] ?? ''));
        if ($location !== '') {
            $rows[] = [
                'label' => $ar ? 'الموقع' : 'Location',
                'value' => $location,
            ];
        }

        $occurredAt = trim((string) ($this->meta['occurred_at'] ?? ''));
        if ($occurredAt !== '') {
            $timezone = trim((string) ($this->meta['timezone_label'] ?? ''));
            if ($timezone === '') {
                $timezone = $ar ? 'بتوقيت طرابلس (GMT+2)' : 'Tripoli time (GMT+2)';
            }

            $rows[] = [
                'label' => $ar ? 'الوقت' : 'Time',
                'value' => $occurredAt,
                'hint' => $timezone,
            ];
        }

        if ($rows === [] && $this->variant === self::VARIANT_PASSWORD_CHANGED) {
            $rows[] = [
                'label' => $ar ? 'الحالة' : 'Status',
                'value' => $ar ? 'تم التحديث بنجاح' : 'Updated successfully',
            ];
        }

        return $rows;
    }
}
