<?php

namespace App\Modules\Notifications\Support;

final class NotificationLocales
{
    public const EN = 'en';

    public const AR = 'ar';

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return [self::EN, self::AR];
    }

    public static function normalize(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        if (str_starts_with($locale, 'ar')) {
            return self::AR;
        }

        if (str_starts_with($locale, 'en')) {
            return self::EN;
        }

        return self::AR;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::EN => 'English',
            self::AR => 'Arabic',
        ];
    }
}
