<?php

namespace Tests\Feature;

use App\Models\HomeBanner;
use App\Models\HomeOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_content_is_public_and_returns_active_items(): void
    {
        HomeBanner::query()->create([
            'public_id' => 'bnr_01',
            'title_en' => 'Discover Amazing Destinations',
            'title_ar' => 'اكتشف وجهات مذهلة',
            'subtitle_en' => 'Plan your perfect trip with exclusive deals',
            'subtitle_ar' => 'خطط لرحلتك المثالية',
            'image_url' => 'https://cdn.example.com/banners/hero1.jpg',
            'action_type' => 'none',
            'sort_order' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
        ]);

        HomeBanner::query()->create([
            'public_id' => 'bnr_inactive',
            'title_en' => 'Hidden',
            'image_url' => 'https://cdn.example.com/banners/hidden.jpg',
            'action_type' => 'none',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        HomeOffer::query()->create([
            'public_id' => 'off_ist_01',
            'title_en' => 'Istanbul Flights',
            'title_ar' => 'رحلات إسطنبول',
            'subtitle_en' => 'From $199',
            'badge_en' => '20% OFF',
            'image_url' => 'https://cdn.example.com/offers/istanbul.jpg',
            'accent_color' => '#5F85C3',
            'category' => 'flights',
            'action_type' => 'search_flights',
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
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/v1/home/content?locale=en&platform=ios')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.banners.0.id', 'bnr_01')
            ->assertJsonPath('data.banners.0.title', 'Discover Amazing Destinations')
            ->assertJsonPath('data.offers.0.id', 'off_ist_01')
            ->assertJsonPath('data.offers.0.action_type', 'search_flights')
            ->assertJsonPath('data.offers.0.action_payload.origin', 'MJI')
            ->assertJsonPath('data.offers.0.action_payload.destination', 'IST')
            ->assertJsonPath('data.offers.0.action_payload.trip_type', 'oneWay')
            ->assertJsonPath('data.offers.0.action_payload.depart_date', '2026-09-15')
            ->assertJsonPath('data.offers.0.action_payload.adults', 1)
            ->assertJsonPath('data.offers.0.action_payload.travel_class', 'Economy')
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertHeader('ETag');

        $this->assertCount(1, $response->json('data.banners'));
        $this->assertCount(1, $response->json('data.offers'));
    }

    public function test_locale_and_accept_language_select_arabic_copy(): void
    {
        HomeBanner::query()->create([
            'public_id' => 'bnr_ar',
            'title_en' => 'English Title',
            'title_ar' => 'عنوان عربي',
            'subtitle_en' => 'English subtitle',
            'subtitle_ar' => 'وصف عربي',
            'image_url' => 'https://cdn.example.com/banners/ar.jpg',
            'action_type' => 'none',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/home/banners?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'عنوان عربي')
            ->assertJsonPath('data.0.subtitle', 'وصف عربي');

        $this->withHeader('Accept-Language', 'ar-BH,ar;q=0.9')
            ->getJson('/api/v1/home/banners')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'عنوان عربي');
    }

    public function test_empty_lists_return_arrays(): void
    {
        $this->getJson('/api/v1/home/banners')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Home banners fetched successfully.',
                'data' => [],
                'meta' => [],
            ]);

        $this->getJson('/api/v1/home/content')
            ->assertOk()
            ->assertJsonPath('data.banners', [])
            ->assertJsonPath('data.offers', []);
    }

    public function test_expired_and_future_items_are_hidden(): void
    {
        HomeOffer::query()->create([
            'public_id' => 'off_expired',
            'title_en' => 'Expired',
            'category' => 'other',
            'action_type' => 'none',
            'sort_order' => 1,
            'is_active' => true,
            'ends_at' => now()->subHour(),
        ]);

        HomeOffer::query()->create([
            'public_id' => 'off_future',
            'title_en' => 'Future',
            'category' => 'other',
            'action_type' => 'none',
            'sort_order' => 2,
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/home/offers')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
