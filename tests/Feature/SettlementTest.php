<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_create_period_import_invoice_resolve_and_close(): void
    {
        $finance = $this->makeAdmin('finance_manager');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
            'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
        ]);

        $wallet = ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => '10000.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);

        $matched = $this->createCostedOrder($customer, $provider, 'BK-SET-1', '531.00', '2026-07-05');
        $different = $this->createCostedOrder($customer, $provider, 'BK-SET-2', '400.00', '2026-07-10');
        $missing = $this->createCostedOrder($customer, $provider, 'BK-SET-3', '250.00', '2026-07-12');

        foreach ([$matched, $different, $missing] as $order) {
            ProviderWalletTransaction::query()->create([
                'provider_wallet_id' => $wallet->id,
                'type' => ProviderWalletTransaction::TYPE_DEBIT,
                'amount' => $order->supplier_cost,
                'balance_after' => '0.00',
                'currency' => 'LYD',
                'reference_type' => ProviderWalletTransaction::REFERENCE_ORDER,
                'reference_id' => (string) $order->id,
                'order_id' => $order->id,
                'description' => 'Settlement test debit',
                'created_by' => $finance->id,
            ]);
        }

        $this->actingAs($finance)
            ->post(route('admin.settlements.store', absolute: false), [
                'provider_id' => $provider->id,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'currency' => 'LYD',
                'notes' => 'July BookNow settlement',
            ])
            ->assertRedirect();

        $settlement = Settlement::query()->firstOrFail();

        $this->assertSame(Settlement::STATUS_DRAFT, $settlement->status);
        $this->assertSame(3, $settlement->orders_count);
        $this->assertSame('1181.00', (string) $settlement->expected_cost);
        $this->assertSame('1181.00', (string) $settlement->wallet_debit_total);

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-SET-1,531.00\nBK-SET-2,410.00\nBK-EXTRA,20.00\n",
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $settlement->refresh();

        $this->assertSame(Settlement::STATUS_OPEN, $settlement->status);
        $this->assertSame('961.00', (string) $settlement->supplier_invoice_total);
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_MATCHED)->count());
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_DIFFERENT_COST)->count());
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_MISSING)->count());
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_EXTRA)->count());
        $this->assertSame(3, $settlement->review_count);

        $reviewItems = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->whereIn('status', [
                SettlementItem::STATUS_DIFFERENT_COST,
                SettlementItem::STATUS_MISSING,
                SettlementItem::STATUS_EXTRA,
            ])
            ->get();

        foreach ($reviewItems as $item) {
            $this->actingAs($finance)
                ->post(route('admin.settlements.items.resolve', [$settlement, $item], absolute: false), [
                    'resolution_note' => 'Reviewed and accepted variance for '.$item->booking_reference,
                ])
                ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));
        }

        $this->actingAs($finance)
            ->post(route('admin.settlements.close', $settlement, absolute: false))
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $settlement->refresh();

        $this->assertSame(Settlement::STATUS_CLOSED, $settlement->status);
        $this->assertSame(0, $settlement->review_count);
        $this->assertNotNull($settlement->closed_at);
    }

    public function test_cannot_close_settlement_with_open_review_items(): void
    {
        $finance = $this->makeAdmin('finance_manager');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
        ]);

        $this->createCostedOrder($customer, $provider, 'BK-OPEN-1', '100.00', '2026-07-05');

        $this->actingAs($finance)
            ->post(route('admin.settlements.store', absolute: false), [
                'provider_id' => $provider->id,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'currency' => 'LYD',
            ]);

        $settlement = Settlement::query()->firstOrFail();

        $this->actingAs($finance)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.close', $settlement, absolute: false))
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false))
            ->assertSessionHasErrors('settlement');
    }

    public function test_settlements_index_requires_permission(): void
    {
        $agent = $this->makeAdmin('support_agent');

        $this->actingAs($agent)
            ->get(route('admin.settlements.index', absolute: false))
            ->assertForbidden();

        $finance = $this->makeAdmin('finance_manager');

        $this->actingAs($finance)
            ->get(route('admin.settlements.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settlements/pages/Index', false)
                ->where('can_manage', true));
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

    private function createCostedOrder(
        User $customer,
        Provider $provider,
        string $reference,
        string $cost,
        string $createdAt,
    ): Order {
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
            'external_booking_id' => 'EXT-'.$reference,
            'booking_reference' => $reference,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['route' => 'TIP-CAI'],
            'currency' => 'LYD',
            'total_amount' => number_format((float) $cost / 0.9, 2, '.', ''),
            'selling_price' => number_format((float) $cost / 0.9, 2, '.', ''),
            'supplier_cost' => $cost,
            'profit_amount' => number_format(((float) $cost / 0.9) - (float) $cost, 2, '.', ''),
            'request_payload' => [],
        ]);

        $order->forceFill([
            'created_at' => $createdAt.' 12:00:00',
            'updated_at' => $createdAt.' 12:00:00',
        ])->save();

        return $order->refresh();
    }
}
