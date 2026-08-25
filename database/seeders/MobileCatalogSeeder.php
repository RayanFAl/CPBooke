<?php

namespace Database\Seeders;

use App\Models\MobileCatalogType;
use Illuminate\Database\Seeder;

class MobileCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'public_id' => 'cat_travel_insurance',
                'key' => 'travel_insurance',
                'title_en' => 'Travel insurance',
                'title_ar' => 'تأمين سفر',
                'subtitle_en' => 'Cover your trip from departure to return',
                'subtitle_ar' => 'غطِّ رحلتك من المغادرة حتى العودة',
                'action_type' => 'search_insurance',
                'action_value' => '/insurance/travel',
                'action_payload' => ['subtype' => 'travel'],
                'sort_order' => 1,
                'options_image_url' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_orange_insurance',
                'key' => 'orange_insurance',
                'title_en' => 'Orange insurance',
                'title_ar' => 'تأمين برتقالي',
                'subtitle_en' => 'Vehicle orange card coverage',
                'subtitle_ar' => 'تغطية البطاقة البرتقالية للمركبة',
                'action_type' => 'route',
                'action_value' => '/insurance/orange',
                'action_payload' => ['subtype' => 'orange'],
                'sort_order' => 2,
                'options_image_url' => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_mandatory_insurance',
                'key' => 'mandatory_insurance',
                'title_en' => 'Mandatory insurance',
                'title_ar' => 'تأمين إجباري',
                'subtitle_en' => 'Required vehicle insurance',
                'subtitle_ar' => 'التأمين الإجباري للمركبة',
                'action_type' => 'route',
                'action_value' => '/insurance/mandatory',
                'action_payload' => ['subtype' => 'mandatory'],
                'sort_order' => 3,
                'options_image_url' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_esim',
                'key' => 'esim',
                'title_en' => 'eSIM',
                'title_ar' => 'شريحة eSIM',
                'subtitle_en' => 'Stay connected as soon as you land',
                'subtitle_ar' => 'ابقَ متصلاً فور وصولك',
                'action_type' => 'search_esim',
                'action_value' => '/esim',
                'action_payload' => null,
                'sort_order' => 4,
                'options_image_url' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1556656793-08538906a9f8?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_extra_package',
                'key' => 'extra_package',
                'title_en' => 'Extra package',
                'title_ar' => 'باقة إضافية',
                'subtitle_en' => 'Add-ons and extras for your trip',
                'subtitle_ar' => 'إضافات وباقات لرحلتك',
                'action_type' => 'route',
                'action_value' => '/extras',
                'action_payload' => null,
                'sort_order' => 5,
                'options_image_url' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1200&q=80',
            ],
        ];

        foreach ($types as $type) {
            MobileCatalogType::query()->updateOrCreate(
                ['public_id' => $type['public_id']],
                [
                    'key' => $type['key'],
                    'title_en' => $type['title_en'],
                    'title_ar' => $type['title_ar'],
                    'subtitle_en' => $type['subtitle_en'],
                    'subtitle_ar' => $type['subtitle_ar'],
                    'options_image_url' => $type['options_image_url'],
                    'market_image_url' => $type['market_image_url'],
                    'show_in_options' => true,
                    'show_in_market' => true,
                    'action_type' => $type['action_type'],
                    'action_value' => $type['action_value'],
                    'action_payload' => $type['action_payload'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                    'platforms' => null,
                ],
            );
        }
    }
}
