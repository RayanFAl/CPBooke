<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_print_provider_profile(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        $this->actingAs($admin)
            ->get(route('admin.suppliers.print', $provider, absolute: false))
            ->assertOk()
            ->assertSee($provider->name, false);
    }

    public function test_admin_can_print_provider_wallet_statement(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();
        $wallet = ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => '1000.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.provider-wallets.print', $wallet, absolute: false))
            ->assertOk()
            ->assertSee('Provider ledger statement', false)
            ->assertSee($provider->name, false);
    }

    public function test_admin_can_print_and_export_settlement(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        $settlement = Settlement::query()->create([
            'provider_id' => $provider->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency' => 'LYD',
            'status' => Settlement::STATUS_OPEN,
            'expected_cost' => '100.00',
            'wallet_debit_total' => '100.00',
            'supplier_invoice_total' => '100.00',
            'difference' => '0.00',
            'orders_count' => 1,
            'created_by' => $admin->id,
        ]);

        SettlementItem::query()->create([
            'settlement_id' => $settlement->id,
            'booking_reference' => 'BK-PRINT-1',
            'supplier_cost' => '100.00',
            'wallet_debit' => '100.00',
            'supplier_invoice_cost' => '100.00',
            'difference' => '0.00',
            'status' => SettlementItem::STATUS_MATCHED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settlements.print', $settlement, absolute: false))
            ->assertOk()
            ->assertSee('Settlement report', false)
            ->assertSee('BK-PRINT-1', false);

        $this->actingAs($admin)
            ->get(route('admin.settlements.export.csv', $settlement, absolute: false))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function makeAdmin(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $user->syncRolesByName([$role]);

        return $user;
    }

    private function makeProvider(): Provider
    {
        return Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
            'commission_rate' => '10.00',
            'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
            'default_currency' => 'LYD',
        ]);
    }
}
