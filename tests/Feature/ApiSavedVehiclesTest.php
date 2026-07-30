<?php

namespace Tests\Feature;

use App\Models\SavedVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSavedVehiclesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validCompulsoryPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => SavedVehicle::TYPE_COMPULSORY,
            'label' => 'My Toyota',
            'is_default' => true,
            'beneficiary_name' => 'John Doe',
            'beneficiary_phone' => '+218910000000',
            'email' => 'john@example.com',
            'vehicle_type_id' => 1,
            'vehicle_color_id' => 1,
            'vehicle_licensing_authority_id' => 1,
            'vehicle_manufacture_year' => 2020,
            'vehicle_chassis_number' => 'CHS123456',
            'vehicle_plate_number' => '12345',
            'payload' => 1.5,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function validOrangePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => SavedVehicle::TYPE_ORANGE,
            'label' => 'Border car',
            'is_default' => false,
            'beneficiary_name' => 'Jane Doe',
            'beneficiary_phone' => '+218911111111',
            'email' => 'jane@example.com',
            'vehicle_manufacture_year' => 2018,
            'vehicle_chassis_number' => 'ORG987654',
            'vehicle_plate_number' => '99887',
            'document_type_id' => 14,
            'vehicle_nationality' => 'LBY',
            'address' => 'Tripoli',
        ], $overrides);
    }

    public function test_customer_can_create_a_compulsory_saved_vehicle(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/saved-vehicles', $this->validCompulsoryPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'compulsory')
            ->assertJsonPath('data.beneficiary_name', 'John Doe')
            ->assertJsonPath('data.vehicle_chassis_number', 'CHS123456')
            ->assertJsonPath('data.vehicle_type_id', 1)
            ->assertJsonPath('data.is_default', true);

        $vehicle = SavedVehicle::query()->findOrFail($response->json('data.id'));

        $this->assertSame('+218910000000', $vehicle->beneficiary_phone);
        $this->assertSame(
            SavedVehicle::hashChassis('CHS123456'),
            $vehicle->vehicle_chassis_number_hash,
        );
    }

    public function test_customer_can_create_an_orange_saved_vehicle_without_compulsory_ids(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/saved-vehicles', $this->validOrangePayload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'orange')
            ->assertJsonPath('data.document_type_id', 14)
            ->assertJsonPath('data.vehicle_type_id', null)
            ->assertJsonPath('data.vehicle_nationality', 'LBY');
    }

    public function test_compulsory_vehicle_requires_type_color_and_licensing_ids(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/saved-vehicles', $this->validCompulsoryPayload([
            'vehicle_type_id' => null,
            'vehicle_color_id' => null,
            'vehicle_licensing_authority_id' => null,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_customer_cannot_create_duplicate_chassis_for_same_account(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        SavedVehicle::factory()->for($customer)->create([
            'vehicle_chassis_number' => 'CHS123456',
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/saved-vehicles', $this->validCompulsoryPayload([
            'vehicle_plate_number' => '99999',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.vehicle_chassis_number.0', 'The chassis number has already been saved for this account.');
    }

    public function test_customer_only_sees_their_own_saved_vehicles(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $other = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        SavedVehicle::factory()->for($customer)->create([
            'label' => 'Mine',
            'vehicle_chassis_number' => 'MINE0001',
        ]);
        SavedVehicle::factory()->for($other)->create([
            'label' => 'Theirs',
            'vehicle_chassis_number' => 'THEIRS0001',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/saved-vehicles')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.vehicles.0.label', 'Mine');
    }

    public function test_customer_can_filter_saved_vehicles_by_type(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        SavedVehicle::factory()->for($customer)->create([
            'type' => SavedVehicle::TYPE_COMPULSORY,
            'vehicle_chassis_number' => 'COMP001',
        ]);
        SavedVehicle::factory()->orange()->for($customer)->create([
            'vehicle_chassis_number' => 'ORANGE001',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/saved-vehicles?'.http_build_query(['type' => 'compulsory']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.vehicles.0.type', 'compulsory');

        $this->getJson('/api/v1/saved-vehicles?'.http_build_query(['type' => 'orange']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.vehicles.0.type', 'orange');
    }

    public function test_customer_can_search_saved_vehicles_by_query(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        SavedVehicle::factory()->for($customer)->create([
            'beneficiary_name' => 'Searchable Owner',
            'vehicle_chassis_number' => 'FINDME001',
            'vehicle_plate_number' => '55555',
            'beneficiary_phone' => '+218912222222',
        ]);
        SavedVehicle::factory()->for($customer)->create([
            'beneficiary_name' => 'Other',
            'vehicle_chassis_number' => 'OTHER001',
            'vehicle_plate_number' => '11111',
        ]);

        Sanctum::actingAs($customer);

        $target = SavedVehicle::query()->where('vehicle_chassis_number', 'FINDME001')->firstOrFail();
        $this->assertSame(SavedVehicle::hashPlate('55555'), $target->vehicle_plate_number_hash);
        $this->assertSame(SavedVehicle::hashPhone('+218912222222'), $target->beneficiary_phone_hash);

        $service = app(\App\Modules\Api\SavedVehicles\Services\SavedVehicleService::class);
        $this->assertSame(1, $service->paginateForUser($customer, 'FINDME001')->total());
        $this->assertSame(1, $service->paginateForUser($customer, '55555')->total());
        $this->assertSame(1, $service->paginateForUser($customer, '+218912222222')->total());
        $this->assertSame(1, $service->paginateForUser($customer, 'Searchable')->total());

        $this->getJson('/api/v1/saved-vehicles?'.http_build_query(['query' => 'FINDME001']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.vehicles.0.vehicle_chassis_number', 'FINDME001');

        $this->getJson('/api/v1/saved-vehicles?'.http_build_query(['query' => '55555']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/saved-vehicles?'.http_build_query(['query' => '+218912222222']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_customer_can_update_and_delete_saved_vehicle(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $vehicle = SavedVehicle::factory()->for($customer)->create([
            'vehicle_chassis_number' => 'UPD0001',
            'label' => 'Old',
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson('/api/v1/saved-vehicles/'.$vehicle->id, $this->validCompulsoryPayload([
            'label' => 'Updated Toyota',
            'vehicle_chassis_number' => 'UPD0001',
            'is_default' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('data.label', 'Updated Toyota');

        $this->deleteJson('/api/v1/saved-vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('message', 'Vehicle deleted successfully.');

        $this->assertSoftDeleted('saved_vehicles', ['id' => $vehicle->id]);
    }

    public function test_customer_cannot_access_another_users_vehicle(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $other = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $vehicle = SavedVehicle::factory()->for($other)->create();

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/saved-vehicles/'.$vehicle->id)->assertForbidden();
        $this->putJson('/api/v1/saved-vehicles/'.$vehicle->id, $this->validCompulsoryPayload([
            'vehicle_chassis_number' => 'HACK001',
        ]))->assertForbidden();
        $this->deleteJson('/api/v1/saved-vehicles/'.$vehicle->id)->assertForbidden();
    }

    public function test_guest_cannot_access_saved_vehicles(): void
    {
        $this->getJson('/api/v1/saved-vehicles')->assertUnauthorized();
        $this->postJson('/api/v1/saved-vehicles', $this->validCompulsoryPayload())->assertUnauthorized();
    }
}
