<?php

namespace App\Support\Home;

use App\Modules\Settings\Services\SystemSettingsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/**
 * Admin datetime-local values are wall-clock times in the platform timezone.
 * They are stored in the application timezone so API visibility compares correctly.
 */
final class HomeContentSchedule
{
    public static function timezone(): string
    {
        $candidate = 'Africa/Tripoli';

        try {
            $settingsTz = trim((string) app(SystemSettingsService::class)->current()->timezone);
            if ($settingsTz !== '' && strtoupper($settingsTz) !== 'UTC') {
                $candidate = $settingsTz;
            } elseif ($settingsTz === '' && config('app.timezone') && strtoupper((string) config('app.timezone')) !== 'UTC') {
                $candidate = (string) config('app.timezone');
            }
        } catch (Throwable) {
            $appTz = (string) config('app.timezone', 'Africa/Tripoli');
            if ($appTz !== '' && strtoupper($appTz) !== 'UTC') {
                $candidate = $appTz;
            }
        }

        try {
            new DateTimeZone($candidate);
        } catch (Throwable) {
            return 'Africa/Tripoli';
        }

        return $candidate;
    }

    public static function parseAdminDateTime(mixed $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->clone()->timezone(config('app.timezone', 'UTC'));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return Carbon::parse($raw, self::timezone())
            ->timezone(config('app.timezone', 'UTC'));
    }

    public static function formatForAdmin(?CarbonInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->clone()
            ->timezone(self::timezone())
            ->format('Y-m-d\TH:i');
    }
}
