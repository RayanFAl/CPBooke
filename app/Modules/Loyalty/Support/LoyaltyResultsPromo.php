<?php

namespace App\Modules\Loyalty\Support;

class LoyaltyResultsPromo
{
    public const ACTION_TYPES = [
        'route',
        'url',
        'search_flights',
        'search_hotels',
        'search_insurance',
        'search_esim',
    ];

    /**
     * Default Booke+ results promo card copy for mobile.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'require_active_level' => true,
            'title_ar' => 'استمتع برحلتك أكثر',
            'title_en' => 'Enjoy your trip more',
            'body_ar' => 'يمكنك توفير {percent}% لأنك في {level}',
            'body_en' => 'You can save {percent}% because you are {level}',
            'cta_ar' => 'اعرض المزايا',
            'cta_en' => 'View benefits',
            'action_type' => 'route',
            'action_value' => '/loyalty',
            'flights' => [
                'body_ar' => 'يمكنك توفير {percent}% على الرحلات لأنك في {level}',
                'body_en' => 'You can save {percent}% on flights because you are {level}',
            ],
            'hotels' => [
                'body_ar' => 'يمكنك توفير {percent}% على الفنادق لأنك في {level}',
                'body_en' => 'You can save {percent}% on hotels because you are {level}',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored) || $stored === []) {
            return $defaults;
        }

        $flights = is_array($stored['flights'] ?? null) ? $stored['flights'] : [];
        $hotels = is_array($stored['hotels'] ?? null) ? $stored['hotels'] : [];

        $actionType = (string) ($stored['action_type'] ?? $defaults['action_type']);
        if (! in_array($actionType, self::ACTION_TYPES, true)) {
            $actionType = $defaults['action_type'];
        }

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'require_active_level' => (bool) ($stored['require_active_level'] ?? $defaults['require_active_level']),
            'title_ar' => self::stringOrDefault($stored['title_ar'] ?? null, $defaults['title_ar']),
            'title_en' => self::stringOrDefault($stored['title_en'] ?? null, $defaults['title_en']),
            'body_ar' => self::stringOrDefault($stored['body_ar'] ?? null, $defaults['body_ar']),
            'body_en' => self::stringOrDefault($stored['body_en'] ?? null, $defaults['body_en']),
            'cta_ar' => self::stringOrDefault($stored['cta_ar'] ?? null, $defaults['cta_ar']),
            'cta_en' => self::stringOrDefault($stored['cta_en'] ?? null, $defaults['cta_en']),
            'action_type' => $actionType,
            'action_value' => self::stringOrDefault($stored['action_value'] ?? null, $defaults['action_value']),
            'flights' => [
                'body_ar' => self::stringOrDefault($flights['body_ar'] ?? null, $defaults['flights']['body_ar']),
                'body_en' => self::stringOrDefault($flights['body_en'] ?? null, $defaults['flights']['body_en']),
            ],
            'hotels' => [
                'body_ar' => self::stringOrDefault($hotels['body_ar'] ?? null, $defaults['hotels']['body_ar']),
                'body_en' => self::stringOrDefault($hotels['body_en'] ?? null, $defaults['hotels']['body_en']),
            ],
        ];
    }

    private static function stringOrDefault(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $default;
    }
}
