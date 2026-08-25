<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Support\FinancialContract;
use PHPUnit\Framework\TestCase;

class FinancialContractTest extends TestCase
{
    public function test_contract_table_matches_approved_debit_credit_and_settlement_flags(): void
    {
        $expected = [
            FinancialContract::EVENT_CUSTOMER_PAYMENT_GATEWAY => ['gateway_clearing', 'unearned_revenue', 'no', 'yes', 'customer_payments'],
            FinancialContract::EVENT_CUSTOMER_PAYMENT_CASH => ['cash', 'unearned_revenue', 'no', 'yes', 'customer_payments'],
            FinancialContract::EVENT_CUSTOMER_WALLET_TOPUP => ['cash', 'customer_liability', 'no', 'yes', 'customer_wallet_movements'],
            FinancialContract::EVENT_CUSTOMER_WALLET_SPEND => ['customer_liability', 'unearned_revenue', 'no', 'yes', 'customer_wallet_movements'],
            FinancialContract::EVENT_PROVIDER_DEBIT => ['provider_cost', 'provider_wallet_asset', 'yes', 'yes', 'provider_debits'],
            FinancialContract::EVENT_PROVIDER_CREDIT => ['provider_wallet_asset', 'provider_cost', 'yes', 'yes', 'provider_credits'],
            FinancialContract::EVENT_REFUND_TO_CASH => ['unearned_revenue', 'cash', 'conditional', 'yes', 'refunds'],
            FinancialContract::EVENT_REFUND_TO_WALLET => ['unearned_revenue', 'customer_liability', 'conditional', 'yes', 'refunds'],
            FinancialContract::EVENT_GATEWAY_FEE => ['gateway_fee_expense', 'gateway_clearing', 'no', 'yes', 'gateway_fees'],
            FinancialContract::EVENT_MARKUP_RECOGNIZED => ['unearned_revenue', 'markup_income', 'no', 'yes', 'markup'],
            FinancialContract::EVENT_ADJUSTMENT => ['adjustment_clearing', 'adjustment_clearing', 'conditional', 'yes', 'adjustments'],
        ];

        foreach ($expected as $event => $row) {
            $contract = FinancialContract::event($event);

            $this->assertSame($row[0], $contract['debit'], $event.' debit');
            $this->assertSame($row[1], $contract['credit'], $event.' credit');
            $this->assertSame($row[2], $contract['provider_settlement'], $event.' provider');
            $this->assertSame($row[3], $contract['platform_settlement'], $event.' platform');
            $this->assertSame($row[4], $contract['platform_bucket'], $event.' bucket');
        }
    }

    public function test_wallet_spend_is_not_counted_as_customer_payment_inflow(): void
    {
        $this->assertTrue(FinancialContract::countsAsCustomerPaymentInflow(
            FinancialContract::EVENT_CUSTOMER_PAYMENT_GATEWAY,
        ));
        $this->assertFalse(FinancialContract::countsAsCustomerPaymentInflow(
            FinancialContract::EVENT_CUSTOMER_WALLET_SPEND,
        ));
        $this->assertFalse(FinancialContract::countsAsCustomerPaymentInflow(
            FinancialContract::EVENT_CUSTOMER_WALLET_TOPUP,
        ));
        $this->assertFalse(FinancialContract::postsToProviderSettlement(
            FinancialContract::EVENT_CUSTOMER_WALLET_SPEND,
        ));
    }

    public function test_mixed_gateway_and_wallet_payment_share_consideration_group_but_not_the_same_inflow_bucket(): void
    {
        $gateway = FinancialContract::event(FinancialContract::EVENT_CUSTOMER_PAYMENT_GATEWAY);
        $walletSpend = FinancialContract::event(FinancialContract::EVENT_CUSTOMER_WALLET_SPEND);

        $this->assertSame(
            FinancialContract::GROUP_CUSTOMER_CONSIDERATION,
            $gateway['double_count_group'],
        );
        $this->assertSame(
            FinancialContract::GROUP_CUSTOMER_CONSIDERATION,
            $walletSpend['double_count_group'],
        );
        $this->assertNotSame($gateway['platform_bucket'], $walletSpend['platform_bucket']);
    }

    public function test_provider_debit_is_the_expected_cost_source_for_both_settlements(): void
    {
        $this->assertTrue(FinancialContract::postsToProviderSettlement(
            FinancialContract::EVENT_PROVIDER_DEBIT,
        ));
        $this->assertTrue(FinancialContract::postsToPlatformSettlement(
            FinancialContract::EVENT_PROVIDER_DEBIT,
        ));
        $this->assertFalse(FinancialContract::postsToProviderSettlement(
            FinancialContract::EVENT_GATEWAY_FEE,
        ));
        $this->assertFalse(FinancialContract::postsToProviderSettlement(
            FinancialContract::EVENT_MARKUP_RECOGNIZED,
        ));
    }

    public function test_accept_variance_posts_ledger_while_correct_data_does_not(): void
    {
        $reasons = FinancialContract::adjustmentReasons();

        $this->assertTrue($reasons[FinancialContract::REASON_EXTRA_SUPPLIER_FEE]['posts_ledger']);
        $this->assertSame(
            FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
            $reasons[FinancialContract::REASON_EXTRA_SUPPLIER_FEE]['resolution'],
        );
        $this->assertFalse($reasons[FinancialContract::REASON_DUPLICATE_INVOICE]['posts_ledger']);
        $this->assertSame(
            FinancialContract::RESOLUTION_CORRECT_DATA,
            $reasons[FinancialContract::REASON_PROVIDER_INVOICE_CORRECTION]['resolution'],
        );
    }

    public function test_legacy_wallet_payment_maps_to_wallet_spend_not_gateway_capture(): void
    {
        $this->assertSame(
            FinancialContract::EVENT_CUSTOMER_WALLET_SPEND,
            FinancialContract::eventForLegacyTransaction('payment', 'customer_wallet'),
        );
        $this->assertSame(
            FinancialContract::EVENT_CUSTOMER_PAYMENT_GATEWAY,
            FinancialContract::eventForLegacyTransaction('payment', 'payment_status_paid'),
        );
    }
}
