<?php

namespace App\Http\Controllers\Dev;

use App\Mail\PasswordResetOtpMail;
use App\Mail\SecurityAlertMail;
use App\Modules\Notifications\Mail\TemplateNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class MailPreviewController extends Controller
{
    public function index(): Response
    {
        $samples = [
            [
                'label' => 'أمان — تسجيل دخول (عربي)',
                'url' => route('dev.mail.preview', ['type' => 'security-login', 'locale' => 'ar']),
            ],
            [
                'label' => 'Security — New login (English)',
                'url' => route('dev.mail.preview', ['type' => 'security-login', 'locale' => 'en']),
            ],
            [
                'label' => 'أمان — تغيير كلمة المرور (عربي)',
                'url' => route('dev.mail.preview', ['type' => 'security-password', 'locale' => 'ar']),
            ],
            [
                'label' => 'Security — Password changed (English)',
                'url' => route('dev.mail.preview', ['type' => 'security-password', 'locale' => 'en']),
            ],
            [
                'label' => 'إشعار عام — عربي',
                'url' => route('dev.mail.preview', ['type' => 'notification', 'locale' => 'ar']),
            ],
            [
                'label' => 'Notification — English',
                'url' => route('dev.mail.preview', ['type' => 'notification', 'locale' => 'en']),
            ],
            [
                'label' => 'OTP — عربي',
                'url' => route('dev.mail.preview', ['type' => 'otp', 'locale' => 'ar']),
            ],
            [
                'label' => 'OTP — English',
                'url' => route('dev.mail.preview', ['type' => 'otp', 'locale' => 'en']),
            ],
        ];

        $links = collect($samples)
            ->map(fn (array $sample) => sprintf(
                '<li><a href="%s" target="_blank" rel="noopener">%s</a></li>',
                e($sample['url']),
                e($sample['label']),
            ))
            ->implode('');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booke — معاينة قوالب البريد</title>
    <style>
        body { font-family: Arial, sans-serif; background: #F8FAFD; color: #2C3E50; margin: 0; padding: 32px 16px; }
        main { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #E8EDF3; border-radius: 16px; padding: 28px 32px; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        p { margin: 0 0 20px; color: #7F8C8D; line-height: 1.6; }
        ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 12px; }
        a { display: block; padding: 14px 16px; border-radius: 12px; background: #F3F6FB; color: #3469B2; text-decoration: none; font-weight: 600; }
        a:hover { background: #E8EDF3; }
        .note { margin-top: 24px; padding: 12px 16px; border-radius: 12px; background: #FFF8E1; color: #2C3E50; font-size: 14px; }
    </style>
</head>
<body>
    <main>
        <h1>معاينة قوالب البريد — Booke</h1>
        <p>اختر أحد القوالب لفتحه في تبويب جديد. هذه الصفحة متاحة فقط في بيئة التطوير.</p>
        <ul>{$links}</ul>
        <p class="note">Dev only — <code>APP_DEBUG=true</code></p>
    </main>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function show(Request $request): Response
    {
        $type = (string) $request->query('type', 'notification');
        $locale = (string) $request->query('locale', 'ar');

        $html = match ($type) {
            'otp' => $this->renderOtpPreview($locale),
            'security-login' => $this->renderSecurityPreview(SecurityAlertMail::VARIANT_LOGIN, $locale),
            'security-password' => $this->renderSecurityPreview(SecurityAlertMail::VARIANT_PASSWORD_CHANGED, $locale),
            default => $this->renderNotificationPreview($locale),
        };

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function renderNotificationPreview(string $locale): string
    {
        if ($locale === 'en') {
            return (new TemplateNotificationMail(
                'Payment successful',
                "Hello Ahmed,\n\nYour payment of 250 LYD has been confirmed.\nThank you for booking with Booke.",
                'en',
            ))->render();
        }

        return (new TemplateNotificationMail(
            'تم الدفع بنجاح',
            "مرحباً أحمد،\n\nتم تأكيد دفعتك بقيمة 250 د.ل بنجاح.\nشكراً لحجزك عبر Booke.",
            'ar',
        ))->render();
    }

    private function renderOtpPreview(string $locale): string
    {
        return (new PasswordResetOtpMail(
            otp: '482913',
            expiresInMinutes: 10,
            recipientName: $locale === 'en' ? 'Ahmed' : 'أحمد',
            mailLocale: $locale,
        ))->render();
    }

    private function renderSecurityPreview(string $variant, string $locale): string
    {
        $isArabic = $locale === 'ar';

        return (new SecurityAlertMail(
            variant: $variant,
            recipientName: $isArabic ? 'أحمد' : 'Ahmed',
            mailLocale: $locale,
            meta: [
                'device_name' => 'iPhone 15 Pro · iOS 18',
                'ip' => '41.252.88.14',
                'location' => $isArabic ? 'طرابلس، ليبيا' : 'Tripoli, Libya',
                'occurred_at' => $isArabic ? '21 سبتمبر 2026 · 09:40 ص' : '21 Sep 2026 · 09:40 AM',
                'timezone_label' => $isArabic ? 'بتوقيت طرابلس (GMT+2)' : 'Tripoli time (GMT+2)',
                'cta_url' => rtrim((string) config('app.url'), '/').'/profile',
            ],
        ))->render();
    }
}
