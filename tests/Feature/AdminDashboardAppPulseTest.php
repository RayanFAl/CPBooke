<?php

namespace Tests\Feature;

use App\Models\AiTravelAssistantLog;
use App\Models\AppDownloadEvent;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Models\UserNotificationDevice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardAppPulseTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_surfaces_app_download_search_and_device_stats(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        AppDownloadEvent::query()->create([
            'event_type' => AppDownloadEvent::TYPE_PAGE_VIEW,
            'visitor_hash' => hash('sha256', 'visitor-a'),
            'platform' => 'android',
            'locale' => 'ar',
        ]);
        AppDownloadEvent::query()->create([
            'event_type' => AppDownloadEvent::TYPE_APK_DOWNLOAD,
            'visitor_hash' => hash('sha256', 'visitor-a'),
            'platform' => 'android',
            'version' => '1.1.0',
            'apk_filename' => 'booke-1.1.0+110.apk',
            'locale' => 'ar',
        ]);
        AppDownloadEvent::query()->create([
            'event_type' => AppDownloadEvent::TYPE_APK_DOWNLOAD,
            'visitor_hash' => hash('sha256', 'visitor-b'),
            'platform' => 'web',
            'version' => '1.1.0',
            'apk_filename' => 'booke-1.1.0+110.apk',
            'locale' => 'en',
        ]);

        UserNotificationDevice::query()->create([
            'user_id' => $customer->id,
            'channel' => 'push',
            'platform' => 'android',
            'device_token' => 'token-android-1',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        UserNotificationDevice::query()->create([
            'user_id' => $otherCustomer->id,
            'channel' => 'push',
            'platform' => 'ios',
            'device_token' => 'token-ios-1',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        TravelSearchIntent::query()->create([
            'user_id' => $customer->id,
            'origin' => 'MJI',
            'destination' => 'IST',
            'route_key' => TravelSearchIntent::routeKeyFor('MJI', 'IST', '2026-09-01'),
            'departure_date' => '2026-09-01',
            'last_seen_price' => '850.00',
            'currency' => 'LYD',
            'last_searched_at' => now(),
            'converted_at' => now(),
        ]);
        TravelSearchIntent::query()->create([
            'user_id' => $otherCustomer->id,
            'origin' => 'MJI',
            'destination' => 'IST',
            'route_key' => TravelSearchIntent::routeKeyFor('MJI', 'IST', '2026-09-03'),
            'departure_date' => '2026-09-03',
            'last_seen_price' => '790.00',
            'currency' => 'LYD',
            'last_searched_at' => now(),
        ]);
        TravelSearchIntent::query()->create([
            'user_id' => $otherCustomer->id,
            'origin' => 'MJI',
            'destination' => 'TUN',
            'route_key' => TravelSearchIntent::routeKeyFor('MJI', 'TUN', '2026-09-02'),
            'departure_date' => '2026-09-02',
            'last_seen_price' => '420.00',
            'currency' => 'LYD',
            'last_searched_at' => now(),
        ]);

        AiTravelAssistantLog::query()->create([
            'user_id' => $customer->id,
            'mode' => 'chat',
            'message' => 'flights to Istanbul',
            'intent' => 'flight_search',
            'success' => true,
        ]);

        $customer->createToken('android-app')->accessToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard/pages/Index', false)
                ->where('dashboard.app_pulse.totals.apk_downloads', 2)
                ->where('dashboard.app_pulse.totals.page_views', 1)
                ->where('dashboard.app_pulse.cards.0.key', 'apk_downloads')
                ->where('dashboard.app_pulse.cards.0.value', 2)
                ->where('dashboard.app_pulse.cards.1.key', 'downloaders')
                ->where('dashboard.app_pulse.cards.1.value', 2)
                ->where('dashboard.app_pulse.cards.2.key', 'installed_devices')
                ->where('dashboard.app_pulse.cards.2.value', 2)
                ->where('dashboard.app_pulse.cards.3.key', 'searchers')
                ->where('dashboard.app_pulse.cards.3.value', 2)
                ->where('dashboard.app_pulse.spotlights.2.value', 1)
                ->has('dashboard.app_pulse.charts.platform_mix', 2)
                ->has('dashboard.app_pulse.charts.top_routes', 2)
                ->where('dashboard.app_pulse.charts.top_routes.0.label', 'MJI → IST')
                ->where('dashboard.app_pulse.conversion.searched', 2)
                ->where('dashboard.app_pulse.conversion.viewed_price', 2)
                ->where('dashboard.app_pulse.conversion.booked', 1)
            );
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        return $admin;
    }
}
