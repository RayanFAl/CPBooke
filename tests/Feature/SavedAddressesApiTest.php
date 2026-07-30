<?php

namespace Tests\Feature;

use App\Models\SavedAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedAddressesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_crud_saved_addresses_and_set_default(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/saved-addresses', [
            'title' => 'Home',
            'address' => 'Tripoli, Libya',
            'latitude' => 32.8872,
            'longitude' => 13.1913,
            'is_default' => true,
        ])->assertCreated();

        $homeId = $create->json('data.id');
        $this->assertNotEmpty($homeId);
        $create->assertJsonPath('data.is_default', true);

        $work = $this->postJson('/api/v1/saved-addresses', [
            'title' => 'Work',
            'address' => 'Benghazi, Libya',
            'latitude' => 32.1167,
            'longitude' => 20.0667,
            'is_default' => false,
        ])->assertCreated();

        $workId = $work->json('data.id');

        $this->getJson('/api/v1/saved-addresses')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.addresses.0.is_default', true);

        $this->putJson('/api/v1/saved-addresses/'.$workId, [
            'title' => 'Office',
            'address' => 'Benghazi Center',
            'latitude' => 32.12,
            'longitude' => 20.07,
            'is_default' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Office')
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse(SavedAddress::query()->findOrFail($homeId)->is_default);

        $this->postJson('/api/v1/saved-addresses/'.$homeId.'/set-default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse(SavedAddress::query()->findOrFail($workId)->fresh()->is_default);

        $this->deleteJson('/api/v1/saved-addresses/'.$homeId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertTrue(SavedAddress::query()->findOrFail($workId)->fresh()->is_default);
    }

    public function test_validation_rejects_invalid_coordinates(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/saved-addresses', [
            'title' => 'Bad',
            'address' => 'Somewhere',
            'latitude' => 200,
            'longitude' => 13.1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');
    }

    public function test_user_cannot_access_another_users_address(): void
    {
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $other = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $address = SavedAddress::factory()->create([
            'user_id' => $owner->id,
            'is_default' => true,
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/saved-addresses/'.$address->id)
            ->assertForbidden();
    }
}
