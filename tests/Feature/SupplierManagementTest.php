<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_supplier_profile(): void
    {
        $admin = $this->makeAdmin('super_admin');

        $this->actingAs($admin)
            ->post('/admin/suppliers', [
                'name' => 'BookNow',
                'legal_name' => 'BookNow LLC',
                'key' => 'booknow',
                'status' => 'active',
                'commission_rate' => 12.5,
                'settlement_cycle' => 'monthly',
                'credit_limit' => 50000,
                'default_currency' => 'LYD',
                'contact_name' => 'Ops Desk',
                'contact_email' => 'ops@booknow.test',
                'contact_phone' => '+218910000000',
                'integration_status' => 'live',
                'contract_notes' => 'Annual contract',
                'notes' => 'Primary flight supplier',
                'website' => 'https://booknow.test',
            ])
            ->assertRedirect();

        $supplier = Provider::query()->where('key', 'booknow')->firstOrFail();

        $this->assertSame('12.50', (string) $supplier->commission_rate);
        $this->assertSame('live', $supplier->integration_status);

        $this->actingAs($admin)
            ->get('/admin/suppliers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/suppliers/pages/Index', false)
                ->has('suppliers.data', 1)
            );

        $this->actingAs($admin)
            ->put("/admin/suppliers/{$supplier->id}", [
                'name' => 'BookNow Travel',
                'legal_name' => 'BookNow LLC',
                'key' => 'booknow',
                'status' => 'active',
                'commission_rate' => 8,
                'settlement_cycle' => 'weekly',
                'credit_limit' => 75000,
                'default_currency' => 'LYD',
                'contact_name' => 'Ops Desk',
                'contact_email' => 'ops@booknow.test',
                'contact_phone' => '+218910000000',
                'integration_status' => 'sandbox',
                'contract_notes' => 'Updated',
                'notes' => 'Updated notes',
                'website' => '',
            ])
            ->assertRedirect(route('admin.suppliers.show', $supplier));

        $supplier->refresh();
        $this->assertSame('BookNow Travel', $supplier->name);
        $this->assertSame('8.00', (string) $supplier->commission_rate);
        $this->assertSame('weekly', $supplier->settlement_cycle);
        $this->assertNull($supplier->website);
    }

    public function test_finance_manager_can_view_suppliers(): void
    {
        $viewer = $this->makeAdmin('finance_manager');

        Provider::query()->create([
            'name' => 'Duffel',
            'key' => 'duffel',
            'status' => Provider::STATUS_ACTIVE,
            'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
            'default_currency' => 'USD',
            'integration_status' => Provider::INTEGRATION_SANDBOX,
        ]);

        $this->actingAs($viewer)
            ->get('/admin/suppliers')
            ->assertOk();
    }

    private function makeAdmin(string $role): User
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName([$role]);

        return $admin;
    }
}
