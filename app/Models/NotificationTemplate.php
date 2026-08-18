<?php

namespace App\Models;

use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationLocales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'category',
    'description',
    'subject',
    'body',
    'translations',
    'channels',
    'variables',
    'version',
    'is_active',
])]
class NotificationTemplate extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'variables' => 'array',
            'translations' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the delivery logs generated from this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'template_code', 'code');
    }

    /**
     * Get the enabled channels for this template.
     *
     * @return array<int, string>
     */
    public function enabledChannels(): array
    {
        return collect($this->channels ?? [])
            ->filter(fn (mixed $channel): bool => is_string($channel) && in_array($channel, NotificationChannels::all(), true))
            ->values()
            ->all();
    }

    public function localizedSubject(string $locale = NotificationLocales::EN): ?string
    {
        $locale = NotificationLocales::normalize($locale);

        if ($locale === NotificationLocales::AR) {
            $translated = data_get($this->translations, NotificationLocales::AR.'.subject');

            if (is_string($translated) && trim($translated) !== '') {
                return $translated;
            }
        }

        return $this->subject;
    }

    public function localizedBody(string $locale = NotificationLocales::EN): string
    {
        $locale = NotificationLocales::normalize($locale);

        if ($locale === NotificationLocales::AR) {
            $translated = data_get($this->translations, NotificationLocales::AR.'.body');

            if (is_string($translated) && trim($translated) !== '') {
                return $translated;
            }
        }

        return (string) $this->body;
    }

    public function hasLocale(string $locale): bool
    {
        $locale = NotificationLocales::normalize($locale);

        if ($locale === NotificationLocales::EN) {
            return trim((string) $this->body) !== '';
        }

        return is_string(data_get($this->translations, $locale.'.body'))
            && trim((string) data_get($this->translations, $locale.'.body')) !== '';
    }
}
