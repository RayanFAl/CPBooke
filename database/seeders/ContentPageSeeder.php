<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        ContentPage::query()->updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title_en' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'body_en' => "Privacy Policy\n\nThis Privacy Policy explains how we collect, use, and protect your personal information when you use our mobile application.\n\nPlease replace this placeholder text with your final legal content before publishing to the app stores.",
                'body_ar' => "سياسة الخصوصية\n\nتوضح سياسة الخصوصية هذه كيف نجمع معلوماتك الشخصية ونستخدمها ونحميها عند استخدامك لتطبيقنا.\n\nيرجى استبدال هذا النص بالمحتوى القانوني النهائي قبل النشر على متاجر التطبيقات.",
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        ContentPage::query()->updateOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title_en' => 'Terms and Conditions',
                'title_ar' => 'الشروط والأحكام',
                'body_en' => "Terms and Conditions\n\nBy using this application you agree to these Terms and Conditions.\n\nPlease replace this placeholder text with your final legal content before publishing to the app stores.",
                'body_ar' => "الشروط والأحكام\n\nباستخدامك لهذا التطبيق فإنك توافق على الشروط والأحكام هذه.\n\nيرجى استبدال هذا النص بالمحتوى القانوني النهائي قبل النشر على متاجر التطبيقات.",
                'sort_order' => 2,
                'is_active' => true,
            ],
        );
    }
}
