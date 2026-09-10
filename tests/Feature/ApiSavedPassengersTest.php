<?php

namespace Tests\Feature;

use App\Models\SavedPassenger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSavedPassengersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPassengerPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => SavedPassenger::TYPE_ADT,
            'title' => 'Mr',
            'first_name' => 'RITAJ',
            'last_name' => 'HEMMAL',
            'date_of_birth' => '1995-03-15',
            'gender' => SavedPassenger::GENDER_FEMALE,
            'nationality' => 'SAU',
            'country_of_residence' => 'SAU',
            'document_type' => SavedPassenger::DOCUMENT_PASSPORT,
            'passport_number' => 'P12345678',
            'passport_issue_country' => 'SAU',
            'passport_issue_date' => '2020-01-01',
            'passport_expiry' => '2030-01-01',
            'email' => 'ritaj@example.com',
            'phone' => '+966501234567',
            'seat_preference' => 'window',
            'meal_preference' => 'halal',
            'is_default' => true,
        ], $overrides);
    }

    public function test_customer_can_create_a_saved_passenger(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/saved-passengers', $this->validPassengerPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Passenger saved successfully')
            ->assertJsonPath('data.first_name', 'RITAJ')
            ->assertJsonPath('data.last_name', 'HEMMAL')
            ->assertJsonPath('data.passport_number', 'P12345678')
            ->assertJsonPath('data.is_default', true);

        $passengerId = $response->json('data.id');

        $this->assertDatabaseHas('saved_passengers', [
            'id' => $passengerId,
            'user_id' => $customer->id,
            'first_name' => 'RITAJ',
            'last_name' => 'HEMMAL',
            'is_default' => true,
        ]);

        $passenger = SavedPassenger::query()->findOrFail($passengerId);

        $this->assertSame('P12345678', $passenger->passport_number);
        $this->assertSame('ritaj@example.com', $passenger->email);
        $this->assertSame('+966501234567', $passenger->phone);
        $this->assertNotSame('P12345678', $passenger->getRawOriginal('passport_number'));
        $this->assertSame(
            SavedPassenger::hashPassportNumber('P12345678'),
            $passenger->passport_number_hash,
        );
        $this->assertSame(
            SavedPassenger::hashPhone('+966501234567'),
            $passenger->phone_hash,
        );
    }

    public function test_customer_cannot_create_duplicate_passport_for_same_account(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        SavedPassenger::factory()->for($customer)->create([
            'passport_number' => 'P12345678',
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/saved-passengers', $this->validPassengerPayload([
            'first_name' => 'OTHER',
            'last_name' => 'PERSON',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('errors.passport_number.0', 'The passport number has already been saved for this account.');
    }

    public function test_customer_only_sees_their_own_saved_passengers(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ownedPassenger = SavedPassenger::factory()->for($customer)->create([
            'first_name' => 'SARA',
            'last_name' => 'ALI',
            'passport_number' => 'SARA12345',
            'phone' => '+966500000001',
        ]);

        SavedPassenger::factory()->for($otherCustomer)->create([
            'first_name' => 'FOREIGN',
            'last_name' => 'PASSENGER',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/saved-passengers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.passengers')
            ->assertJsonPath('data.passengers.0.first_name', 'SARA')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/saved-passengers?q=sara')
            ->assertOk()
            ->assertJsonCount(1, 'data.passengers')
            ->assertJsonPath('data.passengers.0.id', $ownedPassenger->id);

        $this->getJson("/api/v1/saved-passengers/{$ownedPassenger->id}")
            ->assertOk()
            ->assertJsonPath('data.first_name', 'SARA');

        $foreignPassenger = SavedPassenger::factory()->for($otherCustomer)->create();

        $this->getJson("/api/v1/saved-passengers/{$foreignPassenger->id}")
            ->assertForbidden();
    }

    public function test_customer_can_update_and_soft_delete_saved_passenger(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $passenger = SavedPassenger::factory()->for($customer)->create([
            'first_name' => 'OLD',
            'last_name' => 'NAME',
            'passport_number' => 'OLD123456',
        ]);

        Sanctum::actingAs($customer);

        $this->putJson("/api/v1/saved-passengers/{$passenger->id}", $this->validPassengerPayload([
            'first_name' => 'UPDATED',
            'last_name' => 'PASSENGER',
            'passport_number' => 'NEW123456',
            'is_default' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('data.first_name', 'UPDATED')
            ->assertJsonPath('data.passport_number', 'NEW123456');

        $this->deleteJson("/api/v1/saved-passengers/{$passenger->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Passenger deleted successfully.');

        $this->assertSoftDeleted('saved_passengers', [
            'id' => $passenger->id,
        ]);

        $this->getJson("/api/v1/saved-passengers/{$passenger->id}")
            ->assertNotFound();
    }

    public function test_customer_can_update_phone_without_passport_conflict(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $passenger = SavedPassenger::factory()->for($customer)->create([
            'passport_number' => 'P12345678',
            'phone' => '+966501234567',
        ]);

        Sanctum::actingAs($customer);

        $this->putJson("/api/v1/saved-passengers/{$passenger->id}", $this->validPassengerPayload([
            'passport_number' => 'P12345678',
            'phone' => '+966509999999',
        ]))
            ->assertOk()
            ->assertJsonPath('data.passport_number', 'P12345678')
            ->assertJsonPath('data.phone', '+966509999999');
    }

    public function test_only_one_default_passenger_is_allowed_per_user(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $firstResponse = $this->postJson('/api/v1/saved-passengers', $this->validPassengerPayload([
            'first_name' => 'FIRST',
            'passport_number' => 'PASS000001',
            'is_default' => true,
        ]))->assertCreated();

        $secondResponse = $this->postJson('/api/v1/saved-passengers', $this->validPassengerPayload([
            'first_name' => 'SECOND',
            'passport_number' => 'PASS000002',
            'is_default' => true,
        ]))->assertCreated();

        $firstId = $firstResponse->json('data.id');
        $secondId = $secondResponse->json('data.id');

        $this->assertFalse(SavedPassenger::query()->findOrFail($firstId)->is_default);
        $this->assertTrue(SavedPassenger::query()->findOrFail($secondId)->is_default);
        $this->assertSame(
            1,
            SavedPassenger::query()->where('user_id', $customer->id)->where('is_default', true)->count(),
        );
    }

    public function test_expired_passport_is_rejected(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/saved-passengers', $this->validPassengerPayload([
            'passport_expiry' => now()->subDay()->format('Y-m-d'),
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['passport_expiry']);
    }

    public function test_search_uses_hash_columns_for_passport_and_phone(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $passenger = SavedPassenger::factory()->for($customer)->create([
            'first_name' => 'AHMED',
            'last_name' => 'ALI',
            'passport_number' => 'HASHPASS123',
            'phone' => '+966501112233',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/saved-passengers?q=HASHPASS123')
            ->assertOk()
            ->assertJsonCount(1, 'data.passengers')
            ->assertJsonPath('data.passengers.0.id', $passenger->id);

        $this->getJson('/api/v1/saved-passengers?q=%2B966501112233')
            ->assertOk()
            ->assertJsonCount(1, 'data.passengers')
            ->assertJsonPath('data.passengers.0.id', $passenger->id);
    }

    public function test_admin_account_cannot_use_saved_passengers_api(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/saved-passengers')->assertForbidden();

        $this->postJson('/api/v1/saved-passengers', $this->validPassengerPayload())
            ->assertForbidden();
    }

    public function test_validation_errors_use_expected_response_format(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/saved-passengers', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'first_name',
                    'last_name',
                    'date_of_birth',
                    'gender',
                    'nationality',
                    'passport_number',
                    'passport_expiry',
                ],
            ]);
    }

    public function test_customer_can_upload_and_delete_passport_image_privately(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $passenger = SavedPassenger::factory()->create([
            'user_id' => $customer->id,
        ]);

        $this->post('/api/v1/saved-passengers/'.$passenger->id.'/passport-image', [
            'file' => UploadedFile::fake()->image('passport.jpg', 800, 600),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_passport_image', true)
            ->assertJsonMissingPath('data.passport_image_path')
            ->assertJsonMissingPath('data.passport_image_url');

        $passenger->refresh();
        $this->assertTrue($passenger->hasPassportImage());
        $this->assertNotNull($passenger->passport_image_uploaded_at);
        Storage::disk('local')->assertExists($passenger->passport_image_path);

        $this->getJson('/api/v1/saved-passengers/'.$passenger->id)
            ->assertOk()
            ->assertJsonPath('data.has_passport_image', true)
            ->assertJsonMissingPath('data.passport_image_url');

        $this->deleteJson('/api/v1/saved-passengers/'.$passenger->id.'/passport-image')
            ->assertOk()
            ->assertJsonPath('data.has_passport_image', false)
            ->assertJsonPath('data.passport_image_uploaded_at', null);

        $passenger->refresh();
        $this->assertFalse($passenger->hasPassportImage());
    }

    public function test_customer_cannot_upload_passport_image_for_another_user(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $other = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $passenger = SavedPassenger::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($other);

        $this->post('/api/v1/saved-passengers/'.$passenger->id.'/passport-image', [
            'file' => UploadedFile::fake()->image('passport.png', 400, 400),
        ], [
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_deleting_passenger_removes_passport_image_file(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $passenger = SavedPassenger::factory()->create([
            'user_id' => $customer->id,
        ]);

        $this->post('/api/v1/saved-passengers/'.$passenger->id.'/passport-image', [
            'file' => UploadedFile::fake()->image('passport.jpg', 500, 500),
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $path = $passenger->fresh()->passport_image_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->deleteJson('/api/v1/saved-passengers/'.$passenger->id)->assertOk();

        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted('saved_passengers', ['id' => $passenger->id]);
    }
}
