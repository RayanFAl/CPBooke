<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
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
}
