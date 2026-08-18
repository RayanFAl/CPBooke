<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertPage(
            ContentPageCatalog::SLUG_PRIVACY_POLICY,
            ContentPageCatalog::CATEGORY_LEGAL,
            null,
            1,
            'Privacy Policy',
            'سياسة الخصوصية',
            $this->html(
                'Privacy Policy',
                'This Privacy Policy explains how we collect, use, and protect your personal information when you use our mobile application.',
                'Please replace this placeholder with your final legal content before publishing to the app stores.',
            ),
            $this->html(
                'سياسة الخصوصية',
                'توضح سياسة الخصوصية هذه كيف نجمع معلوماتك الشخصية ونستخدمها ونحميها عند استخدامك لتطبيقنا.',
                'يرجى استبدال هذا النص بالمحتوى القانوني النهائي قبل النشر على متاجر التطبيقات.',
            ),
        );

        $this->upsertPage(
            ContentPageCatalog::SLUG_TERMS_OF_SERVICE,
            ContentPageCatalog::CATEGORY_LEGAL,
            null,
            2,
            'Terms and Conditions',
            'الشروط والأحكام',
            $this->html(
                'Terms and Conditions',
                'By using this application you agree to these Terms and Conditions.',
                'Please replace this placeholder with your final legal content before publishing to the app stores.',
            ),
            $this->html(
                'الشروط والأحكام',
                'باستخدامك لهذا التطبيق فإنك توافق على الشروط والأحكام هذه.',
                'يرجى استبدال هذا النص بالمحتوى القانوني النهائي قبل النشر على متاجر التطبيقات.',
            ),
        );

        $this->upsertProductPolicy(
            ContentPageCatalog::PRODUCT_FLIGHT,
            10,
            'Flight booking policy',
            'سياسة حجز الطيران',
            'These are CPBooke policies for flight bookings. Show them in the app next to the airline fare rules for the selected offer.',
            'هذه سياسات CPBooke لحجوزات الطيران. تُعرض في التطبيق بجانب قواعد الأجرة (Fare Rules) الخاصة بالعرض المختار.',
        );

        $this->upsertProductPolicy(
            ContentPageCatalog::PRODUCT_HOTEL,
            11,
            'Hotel booking policy',
            'سياسة حجز الفنادق',
            'These are CPBooke policies for hotel bookings. Show them in the app next to the property rules for the selected stay.',
            'هذه سياسات CPBooke لحجوزات الفنادق. تُعرض في التطبيق بجانب قواعد الفندق الخاصة بالإقامة المختارة.',
        );

        $this->upsertProductPolicy(
            ContentPageCatalog::PRODUCT_INSURANCE,
            12,
            'Insurance policy',
            'سياسة التأمين',
            'These are CPBooke policies for travel insurance. Show them in the app next to the insurer coverage terms for the selected plan.',
            'هذه سياسات CPBooke لتأمين السفر. تُعرض في التطبيق بجانب شروط التغطية الخاصة بالخطة المختارة.',
        );

        $this->upsertProductPolicy(
            ContentPageCatalog::PRODUCT_ESIM,
            13,
            'eSIM policy',
            'سياسة eSIM',
            'These are CPBooke policies for eSIM purchases. Show them in the app next to the provider usage terms for the selected plan.',
            'هذه سياسات CPBooke لشراء eSIM. تُعرض في التطبيق بجانب شروط الاستخدام الخاصة بالخطة المختارة.',
        );
    }

    private function upsertProductPolicy(
        string $product,
        int $sortOrder,
        string $titleEn,
        string $titleAr,
        string $introEn,
        string $introAr,
    ): void {
        $slug = ContentPageCatalog::slugForProduct($product) ?? $product.'-policy';

        $this->upsertPage(
            $slug,
            ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
            $product,
            $sortOrder,
            $titleEn,
            $titleAr,
            $this->html($titleEn, $introEn, 'Please replace this placeholder with your final company policy. Provider fare rules remain separate.'),
            $this->html($titleAr, $introAr, 'يرجى استبدال هذا النص بسياسة الشركة النهائية. قواعد الأجرة من المزوّد تبقى منفصلة.'),
        );
    }

    private function upsertPage(
        string $slug,
        string $category,
        ?string $product,
        int $sortOrder,
        string $titleEn,
        string $titleAr,
        string $bodyEn,
        string $bodyAr,
    ): void {
        $match = $product !== null
            ? ['category' => $category, 'product' => $product]
            : ['slug' => $slug];

        ContentPage::query()->updateOrCreate(
            $match,
            [
                'slug' => $slug,
                'category' => $category,
                'product' => $product,
                'title_en' => $titleEn,
                'title_ar' => $titleAr,
                'body_en' => $bodyEn,
                'body_ar' => $bodyAr,
                'url' => null,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }

    private function html(string $title, string $intro, string $note): string
    {
        return '<h1>'.$title.'</h1><p>'.$intro.'</p><p>'.$note.'</p>';
    }
}
