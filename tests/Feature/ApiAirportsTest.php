<?php

namespace Tests\Feature;

use App\Models\FeaturedAirport;
use App\Models\Order;
use App\Models\User;
use App\Modules\Airports\Listeners\RecordAirportTravelOnOrderConfirmed;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Support\Airports\AirportPopularityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAirportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBooknowAirports();
    }

    public function test_guest_cannot_list_airports(): void
    {
        $this->getJson('/api/v1/airports')
            ->assertUnauthorized();
    }

    public function test_customer_can_fetch_featured_airports(): void
    {
        FeaturedAirport::query()->create([
            'airport_key' => 'IATA:TIP',
            'sort_order' => 1,
        ]);

        FeaturedAirport::query()->create([
            'airport_key' => 'IATA:DAM',
            'sort_order' => 2,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports/featured')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.featured.0.code', 'TIP')
            ->assertJsonPath('data.featured.0.order', 1)
            ->assertJsonPath('data.featured.0.name.en', 'Tripoli International Airport')
            ->assertJsonPath('data.featured.1.code', 'DAM')
            ->assertJsonPath('data.featured.1.order', 2);
    }

    public function test_airports_without_search_returns_featured(): void
    {
        FeaturedAirport::query()->create([
            'airport_key' => 'IATA:TIP',
            'sort_order' => 1,
        ]);

        FeaturedAirport::query()->create([
            'airport_key' => 'IATA:DAM',
            'sort_order' => 2,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.airports.0.code', 'TIP')
            ->assertJsonPath('data.airports.0.order', 1)
            ->assertJsonPath('data.airports.1.code', 'DAM')
            ->assertJsonMissingPath('meta.current_page');
    }

    public function test_customer_can_search_airports_by_arabic_country_name(): void
    {
        DB::table('booknow_airports')
            ->where('iata_code', 'TIP')
            ->update(['country_name_ar' => 'ليبيا', 'city_ar' => 'طرابلس', 'name_ar' => 'مطار طرابلس']);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports?q=ليبيا')
            ->assertOk()
            ->assertJsonPath('data.airports.0.code', 'TIP');

        $this->getJson('/api/v1/airports?q=طرابلس')
            ->assertOk()
            ->assertJsonPath('data.airports.0.code', 'TIP');

        $this->getJson('/api/v1/airports?q=مطار')
            ->assertOk()
            ->assertJsonPath('data.airports.0.code', 'TIP');
    }

    public function test_customer_can_search_airports(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports?q=Tripoli')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.airports.0.code', 'TIP')
            ->assertJsonPath('data.airports.0.city.en', 'Tripoli');
    }

    public function test_customer_can_search_airports_using_search_param(): void
    {
        DB::table('booknow_airports')
            ->where('iata_code', 'TIP')
            ->update(['country_name_ar' => 'ليبيا']);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports?search=ليبيا')
            ->assertOk()
            ->assertJsonPath('message', 'Airports fetched successfully.')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.airports.0.code', 'TIP');
    }

    public function test_airport_search_ranks_most_traveled_then_most_searched(): void
    {
        DB::table('airport_stats')->insert([
            [
                'airport_key' => 'IATA:DAM',
                'search_count' => 1,
                'travel_count' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'airport_key' => 'IATA:CAI',
                'search_count' => 20,
                'travel_count' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports?q=International')
            ->assertOk()
            ->assertJsonPath('data.airports.0.code', 'DAM')
            ->assertJsonPath('data.airports.1.code', 'CAI');
    }

    public function test_airports_without_search_fill_with_popular_after_featured(): void
    {
        FeaturedAirport::query()->create([
            'airport_key' => 'IATA:TIP',
            'sort_order' => 1,
        ]);

        FeaturedAirport::query()->create([
            'airport_key' => 'IATA:DAM',
            'sort_order' => 2,
        ]);

        DB::table('airport_stats')->insert([
            'airport_key' => 'IATA:CAI',
            'search_count' => 3,
            'travel_count' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/airports')
            ->assertOk()
            ->assertJsonPath('data.airports.0.code', 'TIP')
            ->assertJsonPath('data.airports.1.code', 'DAM')
            ->assertJsonPath('data.airports.2.code', 'CAI');
    }

    public function test_flight_search_intent_increments_airport_search_counts(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/notifications/search-intents', [
            'origin' => 'TIP',
            'destination' => 'CAI',
            'departure_date' => '2026-09-20',
        ])->assertOk();

        $this->assertDatabaseHas('airport_stats', [
            'airport_key' => 'IATA:TIP',
            'search_count' => 1,
        ]);
        $this->assertDatabaseHas('airport_stats', [
            'airport_key' => 'IATA:CAI',
            'search_count' => 1,
        ]);
    }

    public function test_confirmed_flight_order_increments_travel_counts(): void
    {
        Event::fake();
        Event::assertListening(OrderConfirmed::class, RecordAirportTravelOnOrderConfirmed::class);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 180,
            'details' => [
                'origin' => 'MJI',
                'destination' => 'TUN',
                'segments' => [
                    [
                        'departure_airport' => 'MJI',
                        'arrival_airport' => 'TUN',
                    ],
                ],
            ],
            'request_payload' => ['test' => true],
        ]);

        app(AirportPopularityService::class)->recordTravelFromOrder($order);

        $this->assertDatabaseHas('airport_stats', [
            'airport_key' => 'IATA:MJI',
            'travel_count' => 1,
        ]);
        $this->assertDatabaseHas('airport_stats', [
            'airport_key' => 'IATA:TUN',
            'travel_count' => 1,
        ]);
    }

    public function test_admin_can_toggle_featured_airport_from_edit(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.airports.featured.toggle', 'IATA:TIP'))
            ->assertRedirect();

        $this->assertDatabaseHas('featured_airports', [
            'airport_key' => 'IATA:TIP',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.airports.featured.toggle', 'IATA:TIP'))
            ->assertRedirect();

        $this->assertDatabaseMissing('featured_airports', [
            'airport_key' => 'IATA:TIP',
        ]);
    }

    public function test_admin_can_update_featured_airports(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->put(route('admin.airports.featured.update'), [
                'airports' => [
                    'IATA:AMM',
                    'IATA:CAI',
                ],
            ])
            ->assertRedirect(route('admin.airports.index'));

        $this->assertDatabaseHas('featured_airports', [
            'airport_key' => 'IATA:AMM',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_cannot_feature_more_than_ten_airports(): void
    {
        $admin = $this->adminUser();

        $airports = collect([
            'IATA:TIP', 'IATA:DAM', 'IATA:BEY', 'IATA:AMM', 'IATA:CAI',
            'IATA:DXB', 'IATA:DOH', 'IATA:RUH', 'IATA:JED', 'IATA:IST', 'IATA:AUH',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.airports.index'))
            ->put(route('admin.airports.featured.update'), [
                'airports' => $airports->all(),
            ])
            ->assertSessionHasErrors('airports');
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

    private function seedBooknowAirports(): void
    {
        $airports = [
            ['iata_code' => 'TIP', 'name_en' => 'Tripoli International Airport', 'city_en' => 'Tripoli', 'country_iso2' => 'LY', 'country_name_en' => 'Libya'],
            ['iata_code' => 'DAM', 'name_en' => 'Damascus International Airport', 'city_en' => 'Damascus', 'country_iso2' => 'SY', 'country_name_en' => 'Syria'],
            ['iata_code' => 'BEY', 'name_en' => 'Beirut Rafic Hariri International Airport', 'city_en' => 'Beirut', 'country_iso2' => 'LB', 'country_name_en' => 'Lebanon'],
            ['iata_code' => 'AMM', 'name_en' => 'Queen Alia International Airport', 'city_en' => 'Amman', 'country_iso2' => 'JO', 'country_name_en' => 'Jordan'],
            ['iata_code' => 'CAI', 'name_en' => 'Cairo International Airport', 'city_en' => 'Cairo', 'country_iso2' => 'EG', 'country_name_en' => 'Egypt'],
        ];

        foreach ($airports as $airport) {
            DB::table('booknow_airports')->insert($airport);
        }
    }
}
