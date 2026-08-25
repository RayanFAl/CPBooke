<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MobileCatalogType extends Model
{
    use HasFactory;

    public const ACTION_TYPES = [
        'none',
        'route',
        'url',
        'search_insurance',
        'search_esim',
    ];

    protected $fillable = [
        'public_id',
        'key',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'options_image_path',
        'options_image_url',
        'market_image_path',
        'market_image_url',
        'show_in_options',
        'show_in_market',
        'action_type',
        'action_value',
        'action_payload',
        'sort_order',
        'is_active',
        'platforms',
    ];

    protected function casts(): array
    {
        return [
            'action_payload' => 'array',
            'platforms' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_in_options' => 'boolean',
            'show_in_market' => 'boolean',
        ];
    }

    public function resolvedOptionsImageUrl(): ?string
    {
        return $this->resolvedImage($this->options_image_url, $this->options_image_path);
    }

    public function resolvedMarketImageUrl(): ?string
    {
        return $this->resolvedImage($this->market_image_url, $this->market_image_path);
    }

    public function localizedTitle(string $locale): string
    {
        return $this->localizedField('title', $locale) ?? '';
    }

    public function localizedSubtitle(string $locale): ?string
    {
        return $this->localizedField('subtitle', $locale);
    }

    private function resolvedImage(?string $url, ?string $path): ?string
    {
        if (is_string($url) && $url !== '') {
            return $url;
        }

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function localizedField(string $field, string $locale): ?string
    {
        $preferred = $locale === 'ar' ? "{$field}_ar" : "{$field}_en";
        $fallback = $locale === 'ar' ? "{$field}_en" : "{$field}_ar";

        $value = $this->{$preferred} ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fallbackValue = $this->{$fallback} ?? null;

        return is_string($fallbackValue) && $fallbackValue !== '' ? $fallbackValue : null;
    }
}
