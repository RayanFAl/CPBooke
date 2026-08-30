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
                'public_id' => 'cat_esim',
                'key' => 'esim',
                'title_en' => 'eSIM',
                'title_ar' => 'شريحة eSIM',
                'subtitle_en' => 'Instant data while you travel',
                'subtitle_ar' => 'إنترنت فوري أثناء السفر',
                'action_type' => 'search_esim',
                'action_value' => '/esim-countries',
                'action_payload' => null,
                'sort_order' => 1,
                'show_in_options' => true,
                'show_in_market' => true,
                'options_image_url' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1556656793-08538906a9f8?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_travel_insurance',
                'key' => 'travel_insurance',
                'title_en' => 'Travel insurance',
                'title_ar' => 'تأمين السفر',
                'subtitle_en' => 'Cover your trip',
                'subtitle_ar' => 'تغطية رحلتك',
                'action_type' => 'search_insurance',
                'action_value' => '/travel-insurance',
                'action_payload' => ['subtype' => 'travel'],
                'sort_order' => 2,
                'show_in_options' => true,
                'show_in_market' => true,
                'options_image_url' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_orange_insurance',
                'key' => 'orange_insurance',
                'title_en' => 'Orange insurance',
                'title_ar' => 'التأمين البرتقالي',
                'subtitle_en' => 'Vehicle orange card coverage',
                'subtitle_ar' => 'تغطية البطاقة البرتقالية للمركبة',
                'action_type' => 'route',
                'action_value' => '/orange-insurance',
                'action_payload' => ['subtype' => 'orange'],
                'sort_order' => 3,
                'show_in_options' => false,
                'show_in_market' => true,
                'options_image_url' => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'public_id' => 'cat_mandatory_insurance',
                'key' => 'mandatory_insurance',
                'title_en' => 'Compulsory Insurance',
                'title_ar' => 'التأمين الإلزامي',
                'subtitle_en' => 'Required vehicle insurance',
                'subtitle_ar' => 'التأمين الإجباري للمركبة',
                'action_type' => 'route',
                'action_value' => '/vehicle-insurance',
                'action_payload' => ['subtype' => 'mandatory'],
                'sort_order' => 4,
                'show_in_options' => false,
                'show_in_market' => true,
                'options_image_url' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
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
                'show_in_options' => true,
                'show_in_market' => true,
                'options_image_url' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=80',
                'market_image_url' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1200&q=80',
            ],
        ];

        foreach ($types as $type) {
            MobileCatalogType::query()->updateOrCreate(
                ['key' => $type['key']],
                [
                    'public_id' => $type['public_id'],
                    'title_en' => $type['title_en'],
                    'title_ar' => $type['title_ar'],
                    'subtitle_en' => $type['subtitle_en'],
                    'subtitle_ar' => $type['subtitle_ar'],
                    'options_image_url' => $type['options_image_url'],
                    'market_image_url' => $type['market_image_url'],
                    'show_in_options' => $type['show_in_options'],
                    'show_in_market' => $type['show_in_market'],
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
