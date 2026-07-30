<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_refund_creates_pending_approval_without_executing(): void
    {
        [$supportAgent, $customer] = $this->supportActors();
        $order = $this->createPaidOrder($customer, '220.00');
        $ticket = $this->createSupportTicket($supportAgent, $customer, $order);

        $this->actingAs($supportAgent)
            ->post(route('admin.support.order.full-refund', $ticket, absolute: false), [
                'reason' => 'Customer requested full refund.',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $approval = Approval::query()->firstOrFail();

        $this->assertSame(Approval::TYPE_REFUND, $approval->type);
        $this->assertSame(Approval::STATUS_PENDING, $approval->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->refresh()->payment_status);
        $this->assertArrayHasKey('snapshot', $approval->payload);
        $this->assertSame('220.00', (string) ($approval->payload['snapshot']['selling_price'] ?? ''));
    }

    public function test_finance_manager_can_approve_pending_refund_and_execute_it(): void
    {
        [$supportAgent, $customer] = $this->supportActors();
        $financeManager = $this->makeAdmin('finance_manager');
        $order = $this->createPaidOrder($customer, '220.00');
        $ticket = $this->createSupportTicket($supportAgent, $customer, $order);

        $this->actingAs($supportAgent)
            ->post(route('admin.support.order.full-refund', $ticket, absolute: false), [
                'reason' => 'Customer requested full refund.',
            ]);

        $approval = Approval::query()->firstOrFail();

        $this->actingAs($financeManager)
            ->post(route('admin.approvals.approve', $approval, absolute: false))
            ->assertRedirect(route('admin.approvals.index', ['status' => 'all'], absolute: false));

        $approval->refresh();
        $order->refresh();

        $this->assertSame(Approval::STATUS_EXECUTED, $approval->status);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->payment_status);
    }

    public function test_small_partial_refund_executes_immediately_for_support_agent(): void
    {
        [$supportAgent, $customer] = $this->supportActors();
        $order = $this->createPaidOrder($customer, '220.00');
        $ticket = $this->createSupportTicket($supportAgent, $customer, $order);

        $this->actingAs($supportAgent)
            ->post(route('admin.support.order.partial-refund', $ticket, absolute: false), [
                'amount' => '50.00',
                'reason' => 'Partial goodwill refund.',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $approval = Approval::query()->firstOrFail();

        $this->assertSame(Approval::STATUS_EXECUTED, $approval->status);
        $this->assertTrue((bool) ($approval->payload['auto_approved'] ?? false));
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->refresh()->payment_status);
    }

    public function test_cancel_on_issued_order_requires_approval(): void
    {
        [$supportAgent, $customer] = $this->supportActors();
        $operationsManager = $this->makeAdmin('operations_manager');
        $order = $this->createPaidOrder($customer, '220.00', Order::STATUS_CONFIRMED);
        $ticket = $this->createSupportTicket($supportAgent, $customer, $order);

        $this->actingAs($supportAgent)
            ->post(route('admin.support.order.cancel', $ticket, absolute: false), [
                'reason' => 'Customer requested cancellation.',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $approval = Approval::query()->firstOrFail();

        $this->assertSame(Approval::TYPE_CANCEL, $approval->type);
        $this->assertSame(Approval::STATUS_PENDING, $approval->status);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->refresh()->status);

        $this->actingAs($operationsManager)
            ->post(route('admin.approvals.approve', $approval, absolute: false))
            ->assertRedirect(route('admin.approvals.index', ['status' => 'all'], absolute: false));

        $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
        $this->assertSame(Approval::STATUS_EXECUTED, $approval->refresh()->status);
    }

    public function test_wallet_deposit_by_finance_manager_creates_pending_approval(): void
    {
        $financeManager = $this->makeAdmin('finance_manager');
        $admin = $this->makeAdmin('admin');
        $wallet = $this->createWallet();

        $this->actingAs($financeManager)
            ->post("/admin/provider-wallets/{$wallet->id}/deposit", [
                'amount' => 1500,
                'note' => 'Monthly top-up',
            ])
            ->assertRedirect(route('admin.provider-wallets.show', $wallet, absolute: false));

        $approval = Approval::query()->firstOrFail();

        $this->assertSame(Approval::TYPE_WALLET_DEPOSIT, $approval->type);
        $this->assertSame(Approval::STATUS_PENDING, $approval->status);
        $this->assertSame('100.00', (string) $wallet->refresh()->balance);

        $this->actingAs($admin)
            ->post(route('admin.approvals.approve', $approval, absolute: false))
            ->assertRedirect(route('admin.approvals.index', ['status' => 'all'], absolute: false));

        $this->assertSame('1600.00', (string) $wallet->refresh()->balance);
        $this->assertSame(Approval::STATUS_EXECUTED, $approval->refresh()->status);
    }

    public function test_finance_manager_cannot_approve_own_wallet_request(): void
    {
        $financeManager = $this->makeAdmin('finance_manager');
        $wallet = $this->createWallet();

        $this->actingAs($financeManager)
            ->post("/admin/provider-wallets/{$wallet->id}/deposit", [
                'amount' => 500,
            ]);

        $approval = Approval::query()->firstOrFail();

        $this->actingAs($financeManager)
            ->post(route('admin.approvals.approve', $approval, absolute: false))
            ->assertSessionHasErrors('approval');
    }

    public function test_approvals_index_is_available_to_finance_manager(): void
    {
        $financeManager = $this->makeAdmin('finance_manager');

        $this->actingAs($financeManager)
            ->get(route('admin.approvals.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/approvals/pages/Index', false)
                ->where('can_approve', true));
    }

    public function test_failed_approval_can_be_retried_without_creating_a_new_request(): void
    {
        [$supportAgent, $customer] = $this->supportActors();
        $operationsManager = $this->makeAdmin('operations_manager');
        $order = $this->createPaidOrder($customer, '220.00', Order::STATUS_CONFIRMED);
        $ticket = $this->createSupportTicket($supportAgent, $customer, $order);

        $this->actingAs($supportAgent)
            ->post(route('admin.support.order.cancel', $ticket, absolute: false), [
                'reason' => 'Customer requested cancellation.',
            ]);

        $approval = Approval::query()->firstOrFail();
        $this->assertSame(Approval::STATUS_PENDING, $approval->status);

        $approval->forceFill([
            'status' => Approval::STATUS_FAILED,
            'approved_by' => $operationsManager->id,
            'approved_at' => now(),
            'execution_error' => 'Simulated provider timeout',
            'executed_at' => now(),
        ])->save();

        $this->actingAs($operationsManager)
            ->post(route('admin.approvals.retry', $approval, absolute: false))
            ->assertRedirect(route('admin.approvals.index', ['status' => 'all'], absolute: false));

        $approval->refresh();
        $order->refresh();

        $this->assertSame(Approval::STATUS_EXECUTED, $approval->status);
        $this->assertNull($approval->execution_error);
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(1, Approval::query()->count());
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function supportActors(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $supportAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $supportAgent->syncRolesByName(['support_agent']);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        return [$supportAgent, $customer];
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

    private function createPaidOrder(User $customer, string $amount, string $status = Order::STATUS_CONFIRMED): Order
    {
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'external_booking_id' => 'EXT-'.$amount,
            'booking_reference' => 'BK-APPROVAL-'.$amount,
            'status' => $status,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['route' => 'TIP-CAI'],
            'currency' => 'LYD',
            'total_amount' => $amount,
            'selling_price' => $amount,
            'supplier_cost' => number_format((float) $amount * 0.9, 2, '.', ''),
            'profit_amount' => number_format((float) $amount * 0.1, 2, '.', ''),
            'request_payload' => ['route' => 'TIP-CAI'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => $amount,
            'currency' => 'LYD',
            'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
            'performed_by_id' => $customer->id,
            'source' => FinancialTransaction::SOURCE_ORDER_CREATION,
            'source_id' => $order->id,
            'reason' => 'Initial order payment.',
            'metadata' => ['workflow_status' => 'executed'],
        ]);

        return $order->refresh();
    }

    private function createSupportTicket(User $agent, User $customer, Order $order): SupportTicket
    {
        return SupportTicket::query()->create([
            'ticket_number' => 'SUP-APPROVAL-'.$order->id,
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $agent->id,
            'subject' => 'Approval workflow ticket',
            'description' => 'Ticket for approval engine coverage.',
        ]);
    }

    private function createWallet(): ProviderWallet
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        return ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => '100.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);
    }
}
