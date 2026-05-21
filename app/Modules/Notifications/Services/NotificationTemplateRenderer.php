<?php

namespace App\Modules\Notifications\Services;

class NotificationTemplateRenderer
{
    /**
     * Render a template string with named placeholders.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(?string $content, array $variables): ?string
    {
        if ($content === null) {
            return null;
        }

        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($variables): string {
            $value = $variables[$matches[1]] ?? '';

            if (is_scalar($value)) {
                return (string) $value;
            }

            if ($value === null) {
                return '';
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }, $content);
    }
}