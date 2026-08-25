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

    public static function publicWebUrl(string $locale = 'en', ?string $slug = null): string
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

        if ($slug === self::SLUG_TERMS_OF_SERVICE) {
            return route('content.pages.show', [
                'slug' => self::SLUG_TERMS_OF_SERVICE,
                'locale' => $locale,
            ]);
        }

        return route('content.pages.index', ['locale' => $locale]);
    }

    /**
     * Fixed admin/mobile workspace tabs (legal + product policies).
     *
     * @return list<array{
     *     tab_id: string,
     *     slug: string,
     *     category: string,
     *     product: string|null,
     *     sort_order: int,
     *     label: string,
     *     label_ar: string,
     *     title_en: string,
     *     title_ar: string,
     *     body_en: string,
     *     body_ar: string
     * }>
     */
    public static function workspaceDefinitions(): array
    {
        return [
            [
                'tab_id' => self::SLUG_PRIVACY_POLICY,
                'slug' => self::SLUG_PRIVACY_POLICY,
                'category' => self::CATEGORY_LEGAL,
                'product' => null,
                'sort_order' => 1,
                'label' => 'Privacy Policy',
                'label_ar' => 'سياسة الخصوصية',
                'title_en' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'body_en' => ContentPageCopy::privacyEn(),
                'body_ar' => ContentPageCopy::privacyAr(),
            ],
            [
                'tab_id' => self::SLUG_TERMS_OF_SERVICE,
                'slug' => self::SLUG_TERMS_OF_SERVICE,
                'category' => self::CATEGORY_LEGAL,
                'product' => null,
                'sort_order' => 2,
                'label' => 'Terms and Conditions',
                'label_ar' => 'الشروط والأحكام',
                'title_en' => 'Terms and Conditions',
                'title_ar' => 'الشروط والأحكام',
                'body_en' => ContentPageCopy::termsEn(),
                'body_ar' => ContentPageCopy::termsAr(),
            ],
            [
                'tab_id' => self::PRODUCT_FLIGHT,
                'slug' => self::productSlugs()[self::PRODUCT_FLIGHT],
                'category' => self::CATEGORY_PRODUCT_POLICY,
                'product' => self::PRODUCT_FLIGHT,
                'sort_order' => 10,
                'label' => self::productLabels()[self::PRODUCT_FLIGHT],
                'label_ar' => self::productLabelsAr()[self::PRODUCT_FLIGHT],
                'title_en' => 'Flight booking policy',
                'title_ar' => 'سياسة حجز الطيران',
                'body_en' => ContentPageCopy::flightEn(),
                'body_ar' => ContentPageCopy::flightAr(),
            ],
            [
                'tab_id' => self::PRODUCT_HOTEL,
                'slug' => self::productSlugs()[self::PRODUCT_HOTEL],
                'category' => self::CATEGORY_PRODUCT_POLICY,
                'product' => self::PRODUCT_HOTEL,
                'sort_order' => 11,
                'label' => self::productLabels()[self::PRODUCT_HOTEL],
                'label_ar' => self::productLabelsAr()[self::PRODUCT_HOTEL],
                'title_en' => 'Hotel booking policy',
                'title_ar' => 'سياسة حجز الفنادق',
                'body_en' => ContentPageCopy::hotelEn(),
                'body_ar' => ContentPageCopy::hotelAr(),
            ],
            [
                'tab_id' => self::PRODUCT_INSURANCE,
                'slug' => self::productSlugs()[self::PRODUCT_INSURANCE],
                'category' => self::CATEGORY_PRODUCT_POLICY,
                'product' => self::PRODUCT_INSURANCE,
                'sort_order' => 12,
                'label' => self::productLabels()[self::PRODUCT_INSURANCE],
                'label_ar' => self::productLabelsAr()[self::PRODUCT_INSURANCE],
                'title_en' => 'Insurance policy',
                'title_ar' => 'سياسة التأمين',
                'body_en' => ContentPageCopy::insuranceEn(),
                'body_ar' => ContentPageCopy::insuranceAr(),
            ],
            [
                'tab_id' => self::PRODUCT_ESIM,
                'slug' => self::productSlugs()[self::PRODUCT_ESIM],
                'category' => self::CATEGORY_PRODUCT_POLICY,
                'product' => self::PRODUCT_ESIM,
                'sort_order' => 13,
                'label' => self::productLabels()[self::PRODUCT_ESIM],
                'label_ar' => self::productLabelsAr()[self::PRODUCT_ESIM],
                'title_en' => 'eSIM policy',
                'title_ar' => 'سياسة eSIM',
                'body_en' => ContentPageCopy::esimEn(),
                'body_ar' => ContentPageCopy::esimAr(),
            ],
        ];
    }

    public static function isWorkspaceTabId(string $tabId): bool
    {
        foreach (self::workspaceDefinitions() as $definition) {
            if ($definition['tab_id'] === $tabId) {
                return true;
            }
        }

        return false;
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
