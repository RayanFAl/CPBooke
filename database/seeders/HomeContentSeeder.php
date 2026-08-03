<?php

namespace Database\Seeders;

use App\Models\HomeBanner;
use App\Models\HomeOffer;
use Illuminate\Database\Seeder;

class HomeContentSeeder extends Seeder
{
    public function run(): void
    {
        HomeBanner::query()->updateOrCreate(
            ['public_id' => 'bnr_01'],
            [
                'title_en' => 'Discover Amazing Destinations',
                'title_ar' => 'اكتشف وجهات مذهلة',
                'subtitle_en' => 'Plan your perfect trip with exclusive deals',
                'subtitle_ar' => 'خطط لرحلتك المثالية مع عروض حصرية',
                'image_url' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1600&q=80',
                'action_type' => 'none',
                'action_value' => null,
                'action_payload' => null,
                'sort_order' => 1,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'platforms' => null,
            ],
        );

        HomeBanner::query()->updateOrCreate(
            ['public_id' => 'bnr_02'],
            [
                'title_en' => 'eSIM Ready When You Land',
                'title_ar' => 'شريحة eSIM جاهزة عند وصولك',
                'subtitle_en' => 'Stay connected abroad in minutes',
                'subtitle_ar' => 'ابقَ متصلاً في الخارج خلال دقائق',
                'image_url' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=1600&q=80',
                'action_type' => 'route',
                'action_value' => '/esim-offer',
                'action_payload' => null,
                'sort_order' => 2,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'platforms' => null,
            ],
        );

        // Replace legacy short payload sample if present.
        HomeOffer::query()->where('public_id', 'off_01')->delete();

        HomeOffer::query()->updateOrCreate(
            ['public_id' => 'off_ist_01'],
            [
                'title_en' => 'Istanbul Flights',
                'title_ar' => 'رحلات إسطنبول',
                'subtitle_en' => 'From 199 LYD',
                'subtitle_ar' => 'ابتداءً من 199 د.ل',
                'badge_en' => '20% OFF',
                'badge_ar' => 'خصم 20%',
                'image_url' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&w=800&q=80',
                'accent_color' => '#5F85C3',
                'category' => 'flights',
                'action_type' => 'search_flights',
                'action_value' => null,
                'action_payload' => [
                    'origin' => 'MJI',
                    'destination' => 'IST',
                    'trip_type' => 'oneWay',
                    'depart_date' => '2026-09-15',
                    'adults' => 1,
                    'travel_class' => 'Economy',
                ],
                'sort_order' => 1,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(2),
                'platforms' => null,
            ],
        );

        HomeOffer::query()->updateOrCreate(
            ['public_id' => 'off_02'],
            [
                'title_en' => 'Travel Insurance',
                'title_ar' => 'تأمين السفر',
                'subtitle_en' => 'Cover your next trip',
                'subtitle_ar' => 'أمّن رحلتك القادمة',
                'badge_en' => 'NEW',
                'badge_ar' => 'جديد',
                'image_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80',
                'accent_color' => '#2F6B4F',
                'category' => 'insurance',
                'action_type' => 'search_insurance',
                'action_value' => null,
                'action_payload' => [
                    'destination' => 'IST',
                    'depart_date' => now()->addMonth()->toDateString(),
                ],
                'sort_order' => 2,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'platforms' => null,
            ],
        );
    }
}
