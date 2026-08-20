<?php

namespace App\Modules\Finance\Support;

/**
 * Canonical debit/credit and settlement-inclusion rules.
 *
 * This is the target posting contract. Existing FinancialTransaction::ledgerMappingForType()
 * remains the live poster until a dedicated migration wires these event keys.
 *
 * Platform snapshot rule: never sum more than one event from the same double_count_group
 * into "customer inflows". Wallet spend is recognition of prepaid liability, not new cash.
 */
final class FinancialContract
{
    public const EVENT_CUSTOMER_PAYMENT_GATEWAY = 'customer_payment_gateway';

    public const EVENT_CUSTOMER_PAYMENT_CASH = 'customer_payment_cash';

    public const EVENT_CUSTOMER_WALLET_TOPUP = 'customer_wallet_topup';

    public const EVENT_CUSTOMER_WALLET_SPEND = 'customer_wallet_spend';

    public const EVENT_PROVIDER_DEBIT = 'provider_debit';

    public const EVENT_PROVIDER_CREDIT = 'provider_credit';

    public const EVENT_REFUND_TO_CASH = 'refund_to_cash';

    public const EVENT_REFUND_TO_WALLET = 'refund_to_wallet';

    public const EVENT_GATEWAY_FEE = 'gateway_fee';

    public const EVENT_MARKUP_RECOGNIZED = 'markup_recognized';

    public const EVENT_ADJUSTMENT = 'adjustment';

    public const ACCOUNT_CASH = 'cash';

    public const ACCOUNT_GATEWAY_CLEARING = 'gateway_clearing';

    public const ACCOUNT_CUSTOMER_LIABILITY = 'customer_liability';

    public const ACCOUNT_UNEARNED_REVENUE = 'unearned_revenue';

    public const ACCOUNT_PROVIDER_WALLET_ASSET = 'provider_wallet_asset';

    public const ACCOUNT_PROVIDER_COST = 'provider_cost';

    public const ACCOUNT_GATEWAY_FEE_EXPENSE = 'gateway_fee_expense';

    public const ACCOUNT_MARKUP_INCOME = 'markup_income';

    public const ACCOUNT_ADJUSTMENT = 'adjustment_clearing';

    public const INCLUSION_YES = 'yes';

    public const INCLUSION_NO = 'no';

    public const INCLUSION_CONDITIONAL = 'conditional';

    public const BUCKET_CUSTOMER_PAYMENTS = 'customer_payments';

    public const BUCKET_CUSTOMER_WALLET_MOVEMENTS = 'customer_wallet_movements';

    public const BUCKET_PROVIDER_DEBITS = 'provider_debits';

    public const BUCKET_PROVIDER_CREDITS = 'provider_credits';

    public const BUCKET_REFUNDS = 'refunds';

    public const BUCKET_GATEWAY_FEES = 'gateway_fees';

    public const BUCKET_MARKUP = 'markup';

    public const BUCKET_ADJUSTMENTS = 'adjustments';

    public const GROUP_CUSTOMER_CONSIDERATION = 'customer_consideration';

    public const REASON_EXTRA_SUPPLIER_FEE = 'extra_supplier_fee';

    public const REASON_PROVIDER_INVOICE_CORRECTION = 'provider_invoice_correction';

    public const REASON_MISSING_ORDER = 'missing_order';

    public const REASON_DUPLICATE_INVOICE = 'duplicate_invoice';

    public const REASON_EXCHANGE_RATE = 'exchange_rate_difference';

    public const REASON_REFUND_DIFFERENCE = 'refund_difference';

    public const REASON_WALLET_DISCREPANCY = 'wallet_discrepancy';

    public const REASON_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const REASON_OTHER = 'other';

    public const RESOLUTION_ACCEPT_VARIANCE = 'accept_variance';

    public const RESOLUTION_CORRECT_DATA = 'correct_data';

    /**
     * @return array<string, array{
     *     debit: string,
     *     credit: string,
     *     provider_settlement: string,
     *     platform_settlement: string,
     *     platform_bucket: string|null,
     *     double_count_group: string|null,
     *     provider_condition: string|null,
     *     notes: string
     * }>
     */
    public static function events(): array
    {
        return [
            self::EVENT_CUSTOMER_PAYMENT_GATEWAY => [
                'debit' => self::ACCOUNT_GATEWAY_CLEARING,
                'credit' => self::ACCOUNT_UNEARNED_REVENUE,
                'provider_settlement' => self::INCLUSION_NO,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_CUSTOMER_PAYMENTS,
                'double_count_group' => self::GROUP_CUSTOMER_CONSIDERATION,
                'provider_condition' => null,
                'notes' => 'Gateway capture. Cash moves when clearing settles; do not also credit customer_liability.',
            ],
            self::EVENT_CUSTOMER_PAYMENT_CASH => [
                'debit' => self::ACCOUNT_CASH,
                'credit' => self::ACCOUNT_UNEARNED_REVENUE,
                'provider_settlement' => self::INCLUSION_NO,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_CUSTOMER_PAYMENTS,
                'double_count_group' => self::GROUP_CUSTOMER_CONSIDERATION,
                'provider_condition' => null,
                'notes' => 'Office/cash collection for the same consideration as a gateway payment.',
            ],
            self::EVENT_CUSTOMER_WALLET_TOPUP => [
                'debit' => self::ACCOUNT_CASH,
                'credit' => self::ACCOUNT_CUSTOMER_LIABILITY,
                'provider_settlement' => self::INCLUSION_NO,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_CUSTOMER_WALLET_MOVEMENTS,
                'double_count_group' => null,
                'provider_condition' => null,
                'notes' => 'Prepaid liability only. Never treated as booking revenue or customer_payments.',
            ],
            self::EVENT_CUSTOMER_WALLET_SPEND => [
                'debit' => self::ACCOUNT_CUSTOMER_LIABILITY,
                'credit' => self::ACCOUNT_UNEARNED_REVENUE,
                'provider_settlement' => self::INCLUSION_NO,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_CUSTOMER_WALLET_MOVEMENTS,
                'double_count_group' => self::GROUP_CUSTOMER_CONSIDERATION,
                'provider_condition' => null,
                'notes' => 'Burns prepaid wallet into the booking. Include in platform wallet movements, never add to customer_payments.',
            ],
            self::EVENT_PROVIDER_DEBIT => [
                'debit' => self::ACCOUNT_PROVIDER_COST,
                'credit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'provider_settlement' => self::INCLUSION_YES,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_PROVIDER_DEBITS,
                'double_count_group' => null,
                'provider_condition' => null,
                'notes' => 'Expected cost and wallet debit for Provider Settlement. Source of Expected Cost once ledger-backed.',
            ],
            self::EVENT_PROVIDER_CREDIT => [
                'debit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'credit' => self::ACCOUNT_PROVIDER_COST,
                'provider_settlement' => self::INCLUSION_YES,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_PROVIDER_CREDITS,
                'double_count_group' => null,
                'provider_condition' => null,
                'notes' => 'Supplier reversal, unused ticket, or provider refund into the prepaid wallet.',
            ],
            self::EVENT_REFUND_TO_CASH => [
                'debit' => self::ACCOUNT_UNEARNED_REVENUE,
                'credit' => self::ACCOUNT_CASH,
                'provider_settlement' => self::INCLUSION_CONDITIONAL,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_REFUNDS,
                'double_count_group' => self::GROUP_CUSTOMER_CONSIDERATION,
                'provider_condition' => 'only_with_matching_provider_credit',
                'notes' => 'Customer money out. Provider settlement only if the supplier cost was also reversed.',
            ],
            self::EVENT_REFUND_TO_WALLET => [
                'debit' => self::ACCOUNT_UNEARNED_REVENUE,
                'credit' => self::ACCOUNT_CUSTOMER_LIABILITY,
                'provider_settlement' => self::INCLUSION_CONDITIONAL,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_REFUNDS,
                'double_count_group' => self::GROUP_CUSTOMER_CONSIDERATION,
                'provider_condition' => 'only_with_matching_provider_credit',
                'notes' => 'Refund restores customer liability. Do not also post refund_to_cash for the same amount.',
            ],
            self::EVENT_GATEWAY_FEE => [
                'debit' => self::ACCOUNT_GATEWAY_FEE_EXPENSE,
                'credit' => self::ACCOUNT_GATEWAY_CLEARING,
                'provider_settlement' => self::INCLUSION_NO,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_GATEWAY_FEES,
                'double_count_group' => null,
                'provider_condition' => null,
                'notes' => 'Expense against clearing, not against booking price. Never part of provider invoice match.',
            ],
            self::EVENT_MARKUP_RECOGNIZED => [
                'debit' => self::ACCOUNT_UNEARNED_REVENUE,
                'credit' => self::ACCOUNT_MARKUP_INCOME,
                'provider_settlement' => self::INCLUSION_NO,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_MARKUP,
                'double_count_group' => null,
                'provider_condition' => null,
                'notes' => 'Reporting split of already-collected consideration. Must not be summed with customer_payments as extra inflow. Until recognition jobs exist, platform markup may be derived: unearned/revenue − provider_cost − refunds.',
            ],
            self::EVENT_ADJUSTMENT => [
                'debit' => self::ACCOUNT_ADJUSTMENT,
                'credit' => self::ACCOUNT_ADJUSTMENT,
                'provider_settlement' => self::INCLUSION_CONDITIONAL,
                'platform_settlement' => self::INCLUSION_YES,
                'platform_bucket' => self::BUCKET_ADJUSTMENTS,
                'double_count_group' => null,
                'provider_condition' => 'reason_posts_to_ledger',
                'notes' => 'Placeholder accounts replaced by reasonAccounts(). Correct-data resolutions must not post.',
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     resolution: string,
     *     posts_ledger: bool,
     *     provider_settlement: string,
     *     debit: string|null,
     *     credit: string|null,
     *     requires_approval: bool
     * }>
     */
    public static function adjustmentReasons(): array
    {
        return [
            self::REASON_EXTRA_SUPPLIER_FEE => [
                'resolution' => self::RESOLUTION_ACCEPT_VARIANCE,
                'posts_ledger' => true,
                'provider_settlement' => self::INCLUSION_YES,
                'debit' => self::ACCOUNT_PROVIDER_COST,
                'credit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'requires_approval' => true,
            ],
            self::REASON_EXCHANGE_RATE => [
                'resolution' => self::RESOLUTION_ACCEPT_VARIANCE,
                'posts_ledger' => true,
                'provider_settlement' => self::INCLUSION_YES,
                'debit' => self::ACCOUNT_PROVIDER_COST,
                'credit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'requires_approval' => true,
            ],
            self::REASON_WALLET_DISCREPANCY => [
                'resolution' => self::RESOLUTION_ACCEPT_VARIANCE,
                'posts_ledger' => true,
                'provider_settlement' => self::INCLUSION_YES,
                'debit' => self::ACCOUNT_PROVIDER_COST,
                'credit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'requires_approval' => true,
            ],
            self::REASON_MANUAL_ADJUSTMENT => [
                'resolution' => self::RESOLUTION_ACCEPT_VARIANCE,
                'posts_ledger' => true,
                'provider_settlement' => self::INCLUSION_YES,
                'debit' => self::ACCOUNT_PROVIDER_COST,
                'credit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'requires_approval' => true,
            ],
            self::REASON_REFUND_DIFFERENCE => [
                'resolution' => self::RESOLUTION_ACCEPT_VARIANCE,
                'posts_ledger' => true,
                'provider_settlement' => self::INCLUSION_CONDITIONAL,
                'debit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'credit' => self::ACCOUNT_PROVIDER_COST,
                'requires_approval' => true,
            ],
            self::REASON_PROVIDER_INVOICE_CORRECTION => [
                'resolution' => self::RESOLUTION_CORRECT_DATA,
                'posts_ledger' => false,
                'provider_settlement' => self::INCLUSION_NO,
                'debit' => null,
                'credit' => null,
                'requires_approval' => false,
            ],
            self::REASON_MISSING_ORDER => [
                'resolution' => self::RESOLUTION_CORRECT_DATA,
                'posts_ledger' => false,
                'provider_settlement' => self::INCLUSION_NO,
                'debit' => null,
                'credit' => null,
                'requires_approval' => false,
            ],
            self::REASON_DUPLICATE_INVOICE => [
                'resolution' => self::RESOLUTION_CORRECT_DATA,
                'posts_ledger' => false,
                'provider_settlement' => self::INCLUSION_NO,
                'debit' => null,
                'credit' => null,
                'requires_approval' => false,
            ],
            self::REASON_OTHER => [
                'resolution' => self::RESOLUTION_ACCEPT_VARIANCE,
                'posts_ledger' => true,
                'provider_settlement' => self::INCLUSION_CONDITIONAL,
                'debit' => self::ACCOUNT_PROVIDER_COST,
                'credit' => self::ACCOUNT_PROVIDER_WALLET_ASSET,
                'requires_approval' => true,
            ],
        ];
    }

    /**
     * @return array{debit: string, credit: string, provider_settlement: string, platform_settlement: string, platform_bucket: string|null, double_count_group: string|null, provider_condition: string|null, notes: string}
     */
    public static function event(string $event): array
    {
        $events = self::events();

        if (! isset($events[$event])) {
            throw new \InvalidArgumentException('Unknown financial contract event: '.$event);
        }

        return $events[$event];
    }

    /**
     * Map a live FinancialTransaction onto the target event key.
     */
    public static function eventForLegacyTransaction(string $type, ?string $source = null): ?string
    {
        return match ($type) {
            'payment' => $source === 'customer_wallet'
                ? self::EVENT_CUSTOMER_WALLET_SPEND
                : self::EVENT_CUSTOMER_PAYMENT_GATEWAY,
            'refund' => self::EVENT_REFUND_TO_CASH,
            'commission' => self::EVENT_MARKUP_RECOGNIZED,
            'adjustment' => self::EVENT_ADJUSTMENT,
            default => null,
        };
    }

    public static function postsToProviderSettlement(string $event): bool
    {
        return self::event($event)['provider_settlement'] === self::INCLUSION_YES;
    }

    public static function postsToPlatformSettlement(string $event): bool
    {
        return self::event($event)['platform_settlement'] === self::INCLUSION_YES;
    }

    public static function countsAsCustomerPaymentInflow(string $event): bool
    {
        return self::event($event)['platform_bucket'] === self::BUCKET_CUSTOMER_PAYMENTS;
    }
}
