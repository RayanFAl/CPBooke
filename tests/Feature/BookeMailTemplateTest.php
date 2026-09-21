<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Mail\SecurityAlertMail;
use App\Modules\Notifications\Mail\TemplateNotificationMail;
use App\Support\Mail\BookeMailTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookeMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_mail_renders_branded_layout_with_rtl_for_arabic(): void
    {
        $mail = new TemplateNotificationMail('تم الدفع بنجاح', "مرحباً أحمد\nشكراً لاستخدامك Booke.", 'ar');
        $html = $mail->render();

        $this->assertStringContainsString(BookeMailTheme::PRIMARY, $html);
        $this->assertStringContainsString(BookeMailTheme::ACCENT, $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('تم الدفع بنجاح', $html);
        $this->assertStringContainsString('مرحباً أحمد', $html);
        $this->assertStringContainsString('support@booke.ly', $html);
        $this->assertStringContainsString('/images/app_logo.png', $html);
    }

    public function test_notification_mail_renders_ltr_for_english(): void
    {
        $mail = new TemplateNotificationMail('Payment successful', "Hello Ahmed,\nThank you for using Booke.", 'en');
        $html = $mail->render();

        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('Payment successful', $html);
        $this->assertStringContainsString('Need help? Contact us at', $html);
    }

    public function test_password_reset_otp_mail_renders_branded_code_block(): void
    {
        $mail = new PasswordResetOtpMail('482913', 10, 'Ahmed', 'en');
        $html = $mail->render();

        $this->assertStringContainsString(BookeMailTheme::PRIMARY, $html);
        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('Password reset code', $html);
    }

    public function test_security_login_mail_renders_clean_layout(): void
    {
        $mail = new SecurityAlertMail(
            SecurityAlertMail::VARIANT_LOGIN,
            'أحمد',
            'ar',
            [
                'device_name' => 'iPhone 15 Pro',
                'ip' => '41.252.88.14',
                'location' => 'طرابلس، ليبيا',
                'occurred_at' => '21 سبتمبر 2026',
                'cta_url' => 'https://booke.ly/profile',
            ],
        );
        $html = $mail->render();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('تم تسجيل دخول جديد إلى حسابك', $html);
        $this->assertStringContainsString('iPhone 15 Pro', $html);
        $this->assertStringContainsString('41.252.88.14', $html);
        $this->assertStringContainsString('Courier New', $html);
        $this->assertStringContainsString('بتوقيت طرابلس', $html);
        $this->assertStringContainsString('مراجعة نشاط الحساب', $html);
        $this->assertStringContainsString('إذا لم تكن أنت من قام بتسجيل الدخول', $html);
        $this->assertStringContainsString('تواصل مع فريق الدعم عبر', $html);
        $this->assertStringContainsString('https://booke.ly/profile', $html);
        $this->assertStringNotContainsString('#070B14', $html);
    }

    public function test_security_password_changed_mail_renders_english_layout(): void
    {
        $mail = new SecurityAlertMail(
            SecurityAlertMail::VARIANT_PASSWORD_CHANGED,
            'Ahmed',
            'en',
            ['cta_url' => 'https://booke.ly/profile'],
        );
        $html = $mail->render();

        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('Your account password was changed', $html);
        $this->assertStringContainsString('Secure my account', $html);
        $this->assertStringContainsString('If you didn&#039;t make this change', $html);
    }
}
