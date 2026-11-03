<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminHotelLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cities_proxies_provider_autocomplete(): void
    {
        $this->configureProvider();

        Http::fake([
            '*/hotels/autocomplete*' => Http::response([
                'data' => [
                    ['city_id' => '42', 'city' => 'Istanbul', 'country' => 'Turkey'],
                ],
            ], 200),
        ]);

        $this->actingAs($this->adminUser())
            ->getJson(route('admin.home.hotel-lookup.cities', ['q' => 'Ist']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cities.0.city_id', '42')
            ->assertJsonPath('data.cities.0.city', 'Istanbul');
    }

    public function test_arabic_tunisia_query_expands_city_list(): void
    {
        $this->configureProvider();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $q = (string) ($request['q'] ?? '');
            if ($q === '') {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
                $q = (string) ($query['q'] ?? '');
            }

            $map = [
                'Tunis' => [['city_id' => '4530', 'city' => 'Tunis', 'country' => 'TUNISIA']],
                'Sousse' => [['city_id' => '4804', 'city' => 'Sousse', 'country' => 'TUNISIA']],
                'Hammamet' => [['city_id' => '17160', 'city' => 'Hammamet', 'country' => 'TUNISIA']],
                'Djerba' => [['city_id' => '1067', 'city' => 'Djerba', 'country' => 'TUNISIA']],
                'Sfax' => [['city_id' => '4738', 'city' => 'Sfax', 'country' => 'TUNISIA']],
                'Monastir' => [['city_id' => '17161', 'city' => 'Monastir', 'country' => 'TUNISIA']],
                'Nabeul' => [['city_id' => '3160', 'city' => 'Nabeul', 'country' => 'TUNISIA']],
                'Mahdia' => [['city_id' => '2773', 'city' => 'Mahdia', 'country' => 'TUNISIA']],
                'Bizerte' => [['city_id' => '1', 'city' => 'Bizerte', 'country' => 'TUNISIA']],
                'Gabes' => [['city_id' => '2', 'city' => 'Gabes', 'country' => 'TUNISIA']],
                'Kairouan' => [['city_id' => '3', 'city' => 'Kairouan', 'country' => 'TUNISIA']],
                'Tabarka' => [['city_id' => '4', 'city' => 'Tabarka', 'country' => 'TUNISIA']],
                'Tozeur' => [['city_id' => '5', 'city' => 'Tozeur', 'country' => 'TUNISIA']],
                'Houmt Souk' => [['city_id' => '6', 'city' => 'Houmt Souk', 'country' => 'TUNISIA']],
            ];

            return Http::response([
                'data' => $map[$q] ?? [],
            ], 200);
        });

        $response = $this->actingAs($this->adminUser())
            ->getJson(route('admin.home.hotel-lookup.cities', ['q' => 'تونس']))
            ->assertOk()
            ->assertJsonPath('success', true);

        $cities = collect($response->json('data.cities'));
        $this->assertTrue($cities->contains(fn ($c) => ($c['city'] ?? '') === 'Tunis'));
        $this->assertTrue($cities->contains(fn ($c) => ($c['city'] ?? '') === 'Sousse'));
        $this->assertTrue($cities->contains(fn ($c) => ($c['city_ar'] ?? '') === 'تونس'));
        $this->assertGreaterThanOrEqual(3, $cities->count());
    }

    public function test_hotels_proxies_provider_search(): void
    {
        $this->configureProvider();

        Http::fake([
            '*/hotels/search' => Http::response([
                'success' => true,
                'data' => [
                    'hotels' => [
                        [
                            'hotel' => [
                                'hotelId' => 'h_1001',
                                'hotelName' => 'Four Seasons',
                                'cityId' => '42',
                                'cityName' => 'Istanbul',
                            ],
                            'source' => '623',
                            'rooms' => [[
                                ['associationId' => 'rate_88', 'name' => 'Deluxe', 'boardName' => 'BB', 'price' => 120, 'currency' => 'EUR'],
                            ]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.home.hotel-lookup.hotels'), [
                'city' => 'Istanbul',
                'city_id' => '42',
                'check_in' => '2026-11-10',
                'check_out' => '2026-11-12',
            ])
            ->assertOk()
            ->assertJsonPath('data.hotels.0.hotel_id', 'h_1001')
            ->assertJsonPath('data.hotels.0.offers.0.offer_id', 'rate_88');
    }

    public function test_offers_uses_details_then_normalizes_rooms(): void
    {
        $this->configureProvider();

        Http::fake([
            '*/hotels/details*' => Http::response([
                'data' => [
                    'rooms' => [
                        ['id' => 'rate_88', 'type' => 'Suite', 'price' => 200, 'currency' => 'EUR'],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->adminUser())
            ->getJson(route('admin.home.hotel-lookup.offers', [
                'hotel_id' => 'h_1001',
                'city_id' => '42',
            ]))
            ->assertOk()
            ->assertJsonPath('data.offers.0.offer_id', 'rate_88')
            ->assertJsonPath('data.offers.0.label', 'Suite');
    }

    public function test_status_reports_unconfigured_without_provider_api(): void
    {
        config([
            'provider_api.base_url' => '',
            'provider_api.access_token' => '',
        ]);

        $this->actingAs($this->adminUser())
            ->getJson(route('admin.home.hotel-lookup.status'))
            ->assertOk()
            ->assertJsonPath('data.configured', false);
    }

    private function configureProvider(): void
    {
        config([
            'provider_api.base_url' => 'https://agency.example.test/agency/median/api/v1',
            'provider_api.access_token' => 'test-token',
            'provider_api.environment' => 'production',
            'provider_api.auth_type' => 'bearer_token',
        ]);

        $provider = Provider::query()->create([
            'key' => Provider::KEY_BOOKNOW,
            'name' => 'BookNow',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => ProviderApiConfig::ENVIRONMENT_PRODUCTION,
            'base_url' => 'https://agency.example.test/agency/median/api/v1',
            'auth_type' => ProviderApiConfig::AUTH_BEARER_TOKEN,
            'access_token' => 'test-token',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 30,
        ]);
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
