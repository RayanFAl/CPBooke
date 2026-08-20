<?php

namespace App\Models;

use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'category',
        'product',
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'url',
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

    protected $attributes = [
        'category' => ContentPageCatalog::CATEGORY_LEGAL,
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCategory(Builder $query, ?string $category): Builder
    {
        if ($category === null || $category === '') {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForProduct(Builder $query, ?string $product): Builder
    {
        if ($product === null || $product === '') {
            return $query;
        }

        return $query->where('product', $product)
            ->where('category', ContentPageCatalog::CATEGORY_PRODUCT_POLICY);
    }

    public function localizedTitle(string $locale): string
    {
        return $this->localizedField('title', $locale) ?? '';
    }

    public function localizedBody(string $locale): string
    {
        return $this->localizedField('body', $locale) ?? '';
    }

    public function publicUrl(?string $locale = null): ?string
    {
        $url = is_string($this->url) ? trim($this->url) : '';

        if ($url !== '') {
            return $url;
        }

        return $this->webUrl($locale ?? 'en');
    }

    public function webUrl(string $locale = 'en'): string
    {
        return ContentPageCatalog::publicWebUrl($locale, (string) $this->slug);
    }

    public function publishedAt(): ?string
    {
        return $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z');
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
