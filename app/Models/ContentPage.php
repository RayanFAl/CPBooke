<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function localizedTitle(string $locale): string
    {
        return $this->localizedField('title', $locale) ?? '';
    }

    public function localizedBody(string $locale): string
    {
        return $this->localizedField('body', $locale) ?? '';
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
