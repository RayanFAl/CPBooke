<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\SettlementItem;
use App\Models\User;
use App\Modules\Finance\Support\FinancialContract;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_create_period_import_invoice_resolve_and_close(): void
    {
        $finance = $this->makeAdmin('super_admin');
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
        $this->assertSame(SettlementItem::COST_SOURCE_ORDER, $settlement->items()->first()->expected_cost_source);

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-SET-1,531.00\nBK-SET-2,410.00\nBK-EXTRA,20.00\n",
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $settlement->refresh();

        $this->assertSame(Settlement::STATUS_PENDING_REVIEW, $settlement->status);
        $this->assertSame('961.00', (string) $settlement->supplier_invoice_total);
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_MATCHED)->count());
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_DIFFERENT_COST)->count());
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_MISSING)->count());
        $this->assertSame(1, SettlementItem::query()->where('status', SettlementItem::STATUS_EXTRA)->count());
        $this->assertSame(3, $settlement->review_count);
        $this->assertSame(1, SettlementAttachment::query()->where('settlement_id', $settlement->id)->count());

        $differentItem = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', SettlementItem::STATUS_DIFFERENT_COST)
            ->firstOrFail();

        $this->actingAs($finance)
            ->post(route('admin.settlements.items.resolve', [$settlement, $differentItem], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'reason' => FinancialContract::REASON_EXTRA_SUPPLIER_FEE,
                'amount' => 10,
                'resolution_note' => 'Supplier charged baggage on BK-SET-2',
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $differentItem->refresh();
        $this->assertSame(SettlementItem::STATUS_RESOLVED, $differentItem->status);
        $this->assertNotNull($differentItem->financial_transaction_id);
        $this->assertSame(
            FinancialTransaction::SOURCE_SETTLEMENT_ADJUSTMENT,
            $differentItem->financialTransaction->source,
        );

        $extra = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', SettlementItem::STATUS_EXTRA)
            ->firstOrFail();

        $this->actingAs($finance)
            ->post(route('admin.settlements.items.resolve', [$settlement, $extra], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_CORRECT_DATA,
                'reason' => FinancialContract::REASON_DUPLICATE_INVOICE,
                'drop_invoice_line' => true,
                'resolution_note' => 'Duplicate invoice line BK-EXTRA',
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $this->assertSame(0, FinancialTransaction::query()->where('source_id', $extra->id)->count());

        $missingItem = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('booking_reference', 'BK-SET-3')
            ->firstOrFail();

        $this->actingAs($finance)
            ->post(route('admin.settlements.items.resolve', [$settlement, $missingItem], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_CORRECT_DATA,
                'reason' => FinancialContract::REASON_MISSING_ORDER,
                'supplier_invoice_cost' => 250,
                'resolution_note' => 'Invoice line was missing from the first import',
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $missingItem->refresh();
        $this->assertSame(SettlementItem::STATUS_MATCHED, $missingItem->status);
        $this->assertNull($missingItem->financial_transaction_id);

        $settlement->refresh();
        $this->assertSame(Settlement::STATUS_OPEN, $settlement->status);

        $this->actingAs($finance)
            ->post(route('admin.settlements.close', $settlement, absolute: false))
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $settlement->refresh();

        $this->assertSame(Settlement::STATUS_CLOSED, $settlement->status);
        $this->assertSame(0, $settlement->review_count);
        $this->assertNotNull($settlement->closed_at);
        $this->assertNotEmpty($settlement->close_history);

        $this->actingAs($finance)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-SET-1,531.00\n",
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false))
            ->assertSessionHasErrors('settlement');
    }

    public function test_note_only_resolve_is_rejected_and_accept_variance_requires_approval(): void
    {
        $finance = $this->makeAdmin('finance_manager');
        $approver = $this->makeAdmin('admin');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow-approval',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
        ]);
        $this->createCostedOrder($customer, $provider, 'BK-APR-1', '100.00', '2026-07-05');

        $this->actingAs($finance)
            ->post(route('admin.settlements.store', absolute: false), [
                'provider_id' => $provider->id,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'currency' => 'LYD',
            ]);

        $settlement = Settlement::query()->latest('id')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-APR-1,120.00\n",
            ]);

        $item = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', SettlementItem::STATUS_DIFFERENT_COST)
            ->firstOrFail();

        $this->actingAs($finance)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.items.resolve', [$settlement, $item], absolute: false), [
                'resolution_note' => 'Looks fine',
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false))
            ->assertSessionHasErrors('resolution');

        $this->actingAs($finance)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.items.resolve', [$settlement, $item], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'reason' => FinancialContract::REASON_DUPLICATE_INVOICE,
                'amount' => 20,
                'resolution_note' => 'Wrong path for this reason',
            ])
            ->assertSessionHasErrors('resolution');

        $this->actingAs($finance)
            ->post(route('admin.settlements.items.resolve', [$settlement, $item], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'reason' => FinancialContract::REASON_EXTRA_SUPPLIER_FEE,
                'amount' => 20,
                'resolution_note' => 'Accepted extra supplier fee',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertNotNull($item->pending_approval_id);
        $this->assertSame(SettlementItem::STATUS_DIFFERENT_COST, $item->status);
        $this->assertSame(0, FinancialTransaction::query()->where('source', FinancialTransaction::SOURCE_SETTLEMENT_ADJUSTMENT)->count());

        $this->actingAs($finance)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.close', $settlement, absolute: false))
            ->assertSessionHasErrors('settlement');

        $approval = Approval::query()->findOrFail($item->pending_approval_id);

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $approval, absolute: false))
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(SettlementItem::STATUS_RESOLVED, $item->status);
        $this->assertNotNull($item->financial_transaction_id);
        $this->assertNull($item->pending_approval_id);
    }

    public function test_reopen_keeps_close_history_and_requires_reason(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow-reopen',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
        ]);
        $this->createCostedOrder($customer, $provider, 'BK-RE-1', '80.00', '2026-07-05');

        $this->actingAs($admin)->post(route('admin.settlements.store', absolute: false), [
            'provider_id' => $provider->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency' => 'LYD',
        ]);

        $settlement = Settlement::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
            'csv_text' => "booking_reference,amount\nBK-RE-1,80.00\n",
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settlements.close', $settlement, absolute: false))
            ->assertRedirect();

        $closedAt = $settlement->refresh()->closed_at?->toIso8601String();

        $this->actingAs($admin)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.reopen', $settlement, absolute: false), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.settlements.reopen', $settlement, absolute: false), [
                'reason' => 'Provider sent a revised August invoice',
            ])
            ->assertRedirect();

        $settlement->refresh();
        $this->assertSame(Settlement::STATUS_REOPENED, $settlement->status);
        $this->assertSame($closedAt, $settlement->closed_at?->toIso8601String());
        $this->assertNotNull($settlement->reopened_at);
        $this->assertNotEmpty($settlement->close_history);
    }

    public function test_negative_adjustment_is_rejected_and_reimport_keeps_single_item_record(): void
    {
        $finance = $this->makeAdmin('finance_manager');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow-negative-adjustment',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
        ]);

        $this->createCostedOrder($customer, $provider, 'BK-NEG-1', '100.00', '2026-07-05');

        $this->actingAs($finance)
            ->post(route('admin.settlements.store', absolute: false), [
                'provider_id' => $provider->id,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'currency' => 'LYD',
            ]);

        $settlement = Settlement::query()->latest('id')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-NEG-1,120.00\n",
            ]);

        $item = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', SettlementItem::STATUS_DIFFERENT_COST)
            ->firstOrFail();

        $this->actingAs($finance)
            ->from(route('admin.settlements.show', $settlement, absolute: false))
            ->post(route('admin.settlements.items.resolve', [$settlement, $item], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'reason' => FinancialContract::REASON_EXTRA_SUPPLIER_FEE,
                'amount' => -20,
                'resolution_note' => 'Negative adjustment should be rejected',
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false))
            ->assertSessionHasErrors('amount');

        $this->assertNull($item->fresh()->pending_approval_id);

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-NEG-1,120.00\n",
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $this->assertSame(1, $settlement->fresh()->items()->count());
        $this->assertSame(2, $settlement->fresh()->invoiceImports()->count());
    }

    public function test_reimport_of_same_invoice_does_not_duplicate_items_and_stales_resolved_variance(): void
    {
        $finance = $this->makeAdmin('finance_manager');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow-reimport',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
        ]);

        $this->createCostedOrder($customer, $provider, 'BK-REIM-1', '100.00', '2026-07-05');

        $this->actingAs($finance)
            ->post(route('admin.settlements.store', absolute: false), [
                'provider_id' => $provider->id,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'currency' => 'LYD',
            ]);

        $settlement = Settlement::query()->latest('id')->firstOrFail();
        $csv = "booking_reference,amount\nBK-REIM-1,120.00\n";

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => $csv,
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $item = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', SettlementItem::STATUS_DIFFERENT_COST)
            ->firstOrFail();

        $this->actingAs($finance)
            ->post(route('admin.settlements.items.resolve', [$settlement, $item], absolute: false), [
                'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'reason' => FinancialContract::REASON_EXTRA_SUPPLIER_FEE,
                'amount' => 20,
                'resolution_note' => 'Accepted 20 variance',
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => $csv,
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $this->assertSame(1, $settlement->fresh()->items()->count());

        $this->actingAs($finance)
            ->post(route('admin.settlements.import-invoice', $settlement, absolute: false), [
                'csv_text' => "booking_reference,amount\nBK-REIM-1,100.00\n",
            ])
            ->assertRedirect(route('admin.settlements.show', $settlement, absolute: false));

        $updated = $settlement->fresh()->items()->firstOrFail();
        $this->assertNotSame(SettlementItem::STATUS_RESOLVED, $updated->status);
        $this->assertSame(1, $settlement->fresh()->invoiceImports()->count());
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
