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
                'title_en' => 'Tripoli → Istanbul Flights',
                'title_ar' => 'رحلات طرابلس → إسطنبول',
                'subtitle_en' => 'One-way deals from Mitiga',
                'subtitle_ar' => 'عروض ذهاب فقط من مطار معيتيقة',
                'image_url' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1600&q=80',
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
                'ends_at' => \Illuminate\Support\Carbon::parse('2026-09-30 23:59:59'),
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
                'action_value' => '/esim-countries',
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
            ['public_id' => 'off_hotels_01'],
            [
                'title_en' => 'Istanbul Hotels',
                'title_ar' => 'فنادق إسطنبول',
                'subtitle_en' => 'Stay in the heart of the city',
                'subtitle_ar' => 'إقامة في قلب المدينة',
                'badge_en' => '20% OFF',
                'badge_ar' => 'خصم 20%',
                'image_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80',
                'accent_color' => '#5F85C3',
                'category' => 'hotels',
                'action_type' => 'search_hotels',
                'action_value' => null,
                'action_payload' => [
                    'city' => 'Istanbul',
                ],
                'sort_order' => 1,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'platforms' => null,
            ],
        );

        // Keep legacy flight offer id for older installs; retire it in favour of the hotels card.
        HomeOffer::query()->where('public_id', 'off_ist_01')->delete();

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
                    'start_date' => now()->addMonth()->toDateString(),
                    'subtype' => 'travel',
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
