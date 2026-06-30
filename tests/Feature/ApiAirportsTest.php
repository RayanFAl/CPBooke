<?php

namespace Tests\Feature;

use App\Models\FeaturedAirport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
