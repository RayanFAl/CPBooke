<?php

namespace App\Support\Mail;

use App\Modules\Notifications\Support\NotificationLocales;

/**
 * Booke email theme — mirrors Flutter AppColors for consistent branding.
 */
final class BookeMailTheme
{
    public const PRIMARY = '#3469B2';

    public const SECONDARY = '#4A90E2';

    public const ACCENT = '#FFC107';

    public const BACKGROUND = '#F8FAFD';

    public const SURFACE = '#FFFFFF';

    public const CARD_BACKGROUND = '#FCFDFE';

    public const TEXT_PRIMARY = '#2C3E50';

    public const TEXT_SECONDARY = '#7F8C8D';

    public const BORDER = '#E8EDF3';

    public const SUCCESS = '#27AE60';

    public const ERROR = '#E74C3C';

    private function __construct()
    {
    }

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

        return [
            'locale' => $locale,
            'rtl' => $rtl,
            'dir' => $rtl ? 'rtl' : 'ltr',
            'brandName' => (string) config('mail.from.name', config('app.name', 'Booke')),
            'supportEmail' => trim((string) config('mail.addresses.support', '')),
            'colors' => self::colors(),
            'footerHelp' => $rtl
                ? 'هل تحتاج مساعدة؟ تواصل معنا على'
                : 'Need help? Contact us at',
            'footerAutomated' => $rtl
                ? 'هذه رسالة آلية من'
                : 'This is an automated message from',
        ];
    }
}
