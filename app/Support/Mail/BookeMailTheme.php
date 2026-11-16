<?php

namespace App\Support\Mail;

use App\Modules\Notifications\Support\NotificationLocales;
use App\Modules\Settings\Services\SystemSettingsService;
use Throwable;

/**
 * Booke email theme — aligned with admin brand (#2f6db8 / #f0b429).
 */
final class BookeMailTheme
{
    public const PRIMARY = '#2f6db8';

    public const SECONDARY = '#255991';

    public const ACCENT = '#f0b429';

    public const BACKGROUND = '#eef3f9';

    public const SURFACE = '#FFFFFF';

    public const CARD_BACKGROUND = '#FFFFFF';

    public const TEXT_PRIMARY = '#0f172a';

    public const TEXT_SECONDARY = '#64748b';

    public const BORDER = '#e2e8f0';

    public const SUCCESS = '#16a34a';

    public const ERROR = '#e11d48';

    private function __construct() {}

    public static function isRtl(?string $locale): bool
    {
        return NotificationLocales::normalize($locale) === NotificationLocales::AR;
    }

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return [
            'primary' => self::PRIMARY,
            'secondary' => self::SECONDARY,
            'accent' => self::ACCENT,
            'background' => self::BACKGROUND,
            'surface' => self::SURFACE,
            'cardBackground' => self::CARD_BACKGROUND,
            'textPrimary' => self::TEXT_PRIMARY,
            'textSecondary' => self::TEXT_SECONDARY,
            'border' => self::BORDER,
            'success' => self::SUCCESS,
            'error' => self::ERROR,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(?string $locale = null): array
    {
        $locale = NotificationLocales::normalize($locale);
        $rtl = self::isRtl($locale);

        $supportEmail = '';
        $supportPhone = null;
        $brandName = (string) config('mail.from.name', config('app.name', 'Booke'));

        try {
            $settings = app(SystemSettingsService::class);
            $supportEmail = $settings->supportEmail();
            $supportPhone = $settings->supportPhone();
            $brandName = $settings->mailFromName();
        } catch (Throwable) {
            $supportEmail = trim((string) config('mail.addresses.support', ''));
        }

        return [
            'locale' => $locale,
            'rtl' => $rtl,
            'dir' => $rtl ? 'rtl' : 'ltr',
            'align' => $rtl ? 'right' : 'left',
            'brandName' => $brandName,
            'logoPath' => self::logoPath(),
            'logoUrl' => rtrim((string) config('app.url'), '/').'/images/email_logo.png',
            'supportEmail' => $supportEmail,
            'supportPhone' => $supportPhone,
            'colors' => self::colors(),
            'footerHelp' => $rtl
                ? 'هل تحتاج مساعدة؟ تواصل معنا على'
                : 'Need help? Contact us at',
            'footerAutomated' => $rtl
                ? 'هذه رسالة آلية من'
                : 'This is an automated message from',
            'footerRights' => $rtl
                ? 'جميع الحقوق محفوظة'
                : 'All rights reserved',
        ];
    }

    public static function logoPath(): ?string
    {
        $emailLogo = public_path('images/email_logo.png');
        if (is_file($emailLogo)) {
            return $emailLogo;
        }

        $fallback = public_path('images/app_logo.png');

        return is_file($fallback) ? $fallback : null;
    }

    /**
     * Build email-safe body HTML with correct RTL/LTR line direction.
     */
    public static function formatBodyHtml(string $body, ?string $locale = null): string
    {
        $rtl = self::isRtl($locale);
        $dir = $rtl ? 'rtl' : 'ltr';
        $align = $rtl ? 'right' : 'left';
        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $parts = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $parts[] = '<div style="height:12px;line-height:12px;font-size:0;">&nbsp;</div>';
                continue;
            }

            $parts[] = '<p dir="'.$dir.'" style="margin:0 0 12px;direction:'.$dir.';text-align:'.$align.';unicode-bidi:plaintext;font-size:15px;line-height:1.85;color:'.self::TEXT_PRIMARY.';">'
                .e($trimmed)
                .'</p>';
        }

        return implode('', $parts);
    }
}
