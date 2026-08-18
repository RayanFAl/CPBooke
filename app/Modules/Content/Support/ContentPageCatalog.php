<?php

namespace App\Modules\Content\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class ContentPageCatalog
{
    public const CATEGORY_LEGAL = 'legal';

    public const CATEGORY_PRODUCT_POLICY = 'product_policy';

    public const PRODUCT_FLIGHT = 'flight';

    public const PRODUCT_HOTEL = 'hotel';

    public const PRODUCT_INSURANCE = 'insurance';

    public const PRODUCT_ESIM = 'esim';

    public const SLUG_PRIVACY_POLICY = 'privacy-policy';

    public const SLUG_TERMS_OF_SERVICE = 'terms-of-service';

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_LEGAL,
            self::CATEGORY_PRODUCT_POLICY,
        ];
    }

    /**
     * @return list<string>
     */
    public static function products(): array
    {
        return [
            self::PRODUCT_FLIGHT,
            self::PRODUCT_HOTEL,
            self::PRODUCT_INSURANCE,
            self::PRODUCT_ESIM,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_LEGAL => 'Legal',
            self::CATEGORY_PRODUCT_POLICY => 'Product policy',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabelsAr(): array
    {
        return [
            self::CATEGORY_LEGAL => 'قانوني',
            self::CATEGORY_PRODUCT_POLICY => 'سياسة المنتج',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function productLabels(): array
    {
        return [
            self::PRODUCT_FLIGHT => 'Flights',
            self::PRODUCT_HOTEL => 'Hotels',
            self::PRODUCT_INSURANCE => 'Insurance',
            self::PRODUCT_ESIM => 'eSIM',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function productLabelsAr(): array
    {
        return [
            self::PRODUCT_FLIGHT => 'الطيران',
            self::PRODUCT_HOTEL => 'الفنادق',
            self::PRODUCT_INSURANCE => 'التأمين',
            self::PRODUCT_ESIM => 'eSIM',
        ];
    }

    /**
     * @return list<string>
     */
    public static function legalSlugs(): array
    {
        return [
            self::SLUG_PRIVACY_POLICY,
            self::SLUG_TERMS_OF_SERVICE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function productSlugs(): array
    {
        return [
            self::PRODUCT_FLIGHT => 'flight-policy',
            self::PRODUCT_HOTEL => 'hotel-policy',
            self::PRODUCT_INSURANCE => 'insurance-policy',
            self::PRODUCT_ESIM => 'esim-policy',
        ];
    }

    public static function slugForProduct(string $product): ?string
    {
        return self::productSlugs()[$product] ?? null;
    }

    public static function categoryLabel(string $category, string $locale = 'en'): string
    {
        $labels = $locale === 'ar' ? self::categoryLabelsAr() : self::categoryLabels();

        return $labels[$category] ?? $category;
    }

    public static function productLabel(?string $product, string $locale = 'en'): ?string
    {
        if ($product === null || $product === '') {
            return null;
        }

        $labels = $locale === 'ar' ? self::productLabelsAr() : self::productLabels();

        return $labels[$product] ?? $product;
    }

    /**
     * @return array<int, array{value: string, label: string, label_ar: string}>
     */
    public static function categoryOptions(): array
    {
        $arabic = self::categoryLabelsAr();

        return collect(self::categoryLabels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'label_ar' => $arabic[$value] ?? $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, label_ar: string, slug: string}>
     */
    public static function productOptions(): array
    {
        $arabic = self::productLabelsAr();
        $slugs = self::productSlugs();

        return collect(self::productLabels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'label_ar' => $arabic[$value] ?? $label,
                'slug' => $slugs[$value] ?? $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    public static function urlValidationRule(): array
    {
        return [
            'nullable',
            'string',
            'max:2048',
            'url:https',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function productValidationRule(?int $ignorePageId = null): array
    {
        $unique = self::uniqueProductRule($ignorePageId);

        return [
            'nullable',
            'string',
            'required_if:category,'.self::CATEGORY_PRODUCT_POLICY,
            'prohibited_unless:category,'.self::CATEGORY_PRODUCT_POLICY,
            Rule::in(self::products()),
            $unique,
        ];
    }

    private static function uniqueProductRule(?int $ignorePageId = null): Unique
    {
        $unique = Rule::unique('content_pages', 'product')
            ->where(fn ($query) => $query->where('category', self::CATEGORY_PRODUCT_POLICY));

        if ($ignorePageId !== null) {
            $unique->ignore($ignorePageId);
        }

        return $unique;
    }
}
