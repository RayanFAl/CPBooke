<?php

namespace Tests\Feature;

use App\Models\FinancialLedgerEntry;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\Admin\Finance\Services\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialTruthLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_transaction_is_posted_as_balanced_double_entry_rows(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Ledger Provider',
            'booking_reference' => 'BK-LEDGER-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Ledger Suites'],
            'currency' => 'USD',
            'total_amount' => 400.00,
            'request_payload' => ['hotel_name' => 'Ledger Suites'],
        ]);

        $transaction = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '400.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
        ]);

        $entries = $transaction->ledgerEntries()->get();

        $this->assertCount(2, $entries);
        $this->assertSame(
            [FinancialLedgerEntry::ENTRY_TYPE_DEBIT, FinancialLedgerEntry::ENTRY_TYPE_CREDIT],
            $entries->pluck('entry_type')->all(),
        );
        $this->assertSame(
            [FinancialTransaction::ACCOUNT_CASH, FinancialTransaction::ACCOUNT_CUSTOMER_LIABILITY],
            $entries->pluck('account_code')->all(),
        );
        $this->assertTrue(app(FinancialLedgerService::class)->isBalanced($transaction));
    }

    public function test_refund_transaction_posts_balanced_reverse_entries(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Ledger Refund Provider',
            'booking_reference' => 'BK-LEDGER-REFUND-001',
            'status' => Order::STATUS_REFUNDED,
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Ledger Air'],
            'currency' => 'USD',
            'total_amount' => 180.00,
            'request_payload' => ['airline' => 'Ledger Air'],
        ]);

        $transaction = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '75.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_REFUNDED,
        ]);

        $entries = $transaction->ledgerEntries()->get();

        $this->assertCount(2, $entries);
        $this->assertSame(
            [FinancialTransaction::ACCOUNT_CUSTOMER_LIABILITY, FinancialTransaction::ACCOUNT_CASH],
            $entries->pluck('account_code')->all(),
        );
        $this->assertSame('75.00', $entries->first()->amount);
        $this->assertTrue(app(FinancialLedgerService::class)->isBalanced($transaction));
    }
}