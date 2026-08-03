<?php

namespace App\Support\Home\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

trait HasHomeContentVisibility
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyVisible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPlatform(Builder $query, ?string $platform): Builder
    {
        if ($platform === null || $platform === '') {
            return $query;
        }

        $platform = strtolower($platform);

        return $query->where(function (Builder $builder) use ($platform): void {
            $builder
                ->whereNull('platforms')
                ->orWhereJsonContains('platforms', $platform);
        });
    }

    public function resolvedImageUrl(): ?string
    {
        if (is_string($this->image_url) && $this->image_url !== '') {
            return $this->image_url;
        }

        if (! is_string($this->image_path) || $this->image_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function localizedTitle(string $locale): string
    {
        return $this->localizedField('title', $locale) ?? '';
    }

    public function localizedSubtitle(string $locale): ?string
    {
        return $this->localizedField('subtitle', $locale);
    }

    public function localizedBadge(string $locale): ?string
    {
        return $this->localizedField('badge', $locale);
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
