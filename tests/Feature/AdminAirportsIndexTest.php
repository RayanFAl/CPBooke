<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAirportsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAirports();
    }

    public function test_airports_index_defaults_to_name_order(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.airports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/airports/pages/Index', false)
                ->where('airports.data.0.iata_code', 'BEY')
                ->where('airports.data.1.iata_code', 'CAI')
                ->has('country_options')
                ->has('type_options')
            );
    }

    public function test_airports_index_can_filter_by_country_and_type(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.airports.index', [
                'country' => 'LY',
                'type' => 'large_airport',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/airports/pages/Index', false)
                ->where('filters.country', 'LY')
                ->where('filters.type', 'large_airport')
                ->where('airports.total', 1)
                ->where('airports.data.0.iata_code', 'TIP')
            );
    }

    public function test_airports_index_keeps_search_and_ignores_invalid_per_page(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.airports.index', [
                'search' => 'Cairo',
                'per_page' => 999,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/airports/pages/Index', false)
                ->where('filters.search', 'Cairo')
                ->where('filters.per_page', 20)
                ->where('airports.total', 1)
                ->where('airports.data.0.iata_code', 'CAI')
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

    private function seedAirports(): void
    {
        $airports = [
            ['iata_code' => 'TIP', 'icao_code' => 'HLLT', 'name_en' => 'Tripoli International Airport', 'city_en' => 'Tripoli', 'country_iso2' => 'LY', 'country_name_en' => 'Libya', 'type' => 'large_airport'],
            ['iata_code' => 'DAM', 'icao_code' => 'OSDI', 'name_en' => 'Damascus International Airport', 'city_en' => 'Damascus', 'country_iso2' => 'SY', 'country_name_en' => 'Syria', 'type' => 'large_airport'],
            ['iata_code' => 'BEY', 'icao_code' => 'OLBA', 'name_en' => 'Beirut Rafic Hariri International Airport', 'city_en' => 'Beirut', 'country_iso2' => 'LB', 'country_name_en' => 'Lebanon', 'type' => 'large_airport'],
            ['iata_code' => 'AMM', 'icao_code' => 'OJAI', 'name_en' => 'Queen Alia International Airport', 'city_en' => 'Amman', 'country_iso2' => 'JO', 'country_name_en' => 'Jordan', 'type' => 'medium_airport'],
            ['iata_code' => 'CAI', 'icao_code' => 'HECA', 'name_en' => 'Cairo International Airport', 'city_en' => 'Cairo', 'country_iso2' => 'EG', 'country_name_en' => 'Egypt', 'type' => 'large_airport'],
        ];

        foreach ($airports as $airport) {
            DB::table('booknow_airports')->insert($airport);
        }
    }
}
