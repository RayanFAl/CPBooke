<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTypeSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_registration_creates_a_customer_account(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mobile Customer',
            'email' => 'customer@example.com',
            'phone' => '+15550000001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'iPhone',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'customer@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
    }

    public function test_api_login_accepts_customers_and_rejects_admin_accounts(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
            'phone' => '+15550000002',
            'password' => 'password',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'phone' => '+15550000003',
            'password' => 'password',
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['admin']);

        $customerResponse = $this->postJson('/api/v1/auth/login', [
            'login' => $customer->email,
            'password' => 'password',
            'device_name' => 'Android',
        ]);

        $customerResponse
            ->assertOk()
            ->assertJsonPath('data.user.email', $customer->email);

        $adminResponse = $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'password',
            'device_name' => 'Android',
        ]);

        $adminResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('login');
    }

    public function test_dashboard_login_accepts_admins_and_rejects_customers(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer-dashboard@example.com',
            'password' => 'password',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin-dashboard@example.com',
            'password' => 'password',
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['admin']);

        $customerResponse = $this->from('/login')->post('/login', [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $customerResponse
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $adminResponse = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $adminResponse->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin->fresh());
    }

    public function test_dashboard_routes_allow_only_admin_accounts(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['admin']);

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }
}