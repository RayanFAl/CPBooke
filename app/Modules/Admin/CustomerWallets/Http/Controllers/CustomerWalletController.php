<?php

namespace App\Modules\Admin\CustomerWallets\Http\Controllers;

use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\User;
use App\Modules\Admin\CustomerWallets\Http\Requests\CreditCustomerWalletRequest;
use App\Modules\Admin\CustomerWallets\Http\Requests\DebitCustomerWalletRequest;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class CustomerWalletController
{
    public function __construct(
        private readonly CustomerWalletService $walletService,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $wallets = CustomerWallet::query()
            ->with('user:id,name,full_name,email,phone')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('wallet_number', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CustomerWallet $wallet): array => $this->serializeWalletListItem($wallet));

        return Inertia::render('admin/customer-wallets/pages/Index', [
            'wallets' => $wallets,
            'filters' => [
                'search' => $search,
            ],
            'can_manage' => $request->user()?->can('customer-wallets.manage') ?? false,
        ]);
    }

    public function show(Request $request, CustomerWallet $customerWallet): Response
    {
        $customerWallet->loadMissing('user:id,name,full_name,email,phone');

        $transactions = $customerWallet->transactions()
            ->with(['order:id,booking_reference,external_booking_id', 'creator:id,name,full_name'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($transaction): array => $this->serializeTransaction($transaction));

        return Inertia::render('admin/customer-wallets/pages/Show', [
            'wallet' => $this->serializeWallet($customerWallet->refresh()),
            'transactions' => $transactions,
            'credit_reasons' => $this->creditReasons(),
            'open_add_money' => $request->input('action') === 'add-money',
            'receipt_id' => $request->integer('receipt') ?: null,
            'can_manage' => $request->user()?->can('customer-wallets.manage') ?? false,
            'can_view_user' => $request->user()?->can('users.view') ?? false,
        ]);
    }

    public function credit(CreditCustomerWalletRequest $request, CustomerWallet $customerWallet): RedirectResponse
    {
        $data = $request->validated();

        $transaction = $this->walletService->adminCredit(
            $customerWallet,
            $data['amount'],
            $request->user(),
            [
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
            ],
        );

        $transaction->loadMissing('creator:id,name,full_name');

        return redirect()
            ->route('admin.customer-wallets.show', [
                'customerWallet' => $customerWallet,
                'receipt' => $transaction->id,
            ])
            ->with('success', $transaction->adminTopUpSummary() ?: 'Wallet top-up recorded.');
    }

    public function debit(DebitCustomerWalletRequest $request, CustomerWallet $customerWallet): RedirectResponse
    {
        $data = $request->validated();

        $this->walletService->adminDebit(
            $customerWallet,
            $data['amount'],
            $request->user(),
            ['description' => $data['note'] ?? null],
        );

        return redirect()
            ->route('admin.customer-wallets.show', $customerWallet)
            ->with('success', 'Debit recorded.');
    }

    public function freeze(Request $request, CustomerWallet $customerWallet): RedirectResponse
    {
        $this->walletService->freeze($customerWallet, $request->user());

        return redirect()
            ->route('admin.customer-wallets.show', $customerWallet)
            ->with('success', 'Wallet frozen.');
    }

    public function unfreeze(Request $request, CustomerWallet $customerWallet): RedirectResponse
    {
        $this->walletService->unfreeze($customerWallet, $request->user());

        return redirect()
            ->route('admin.customer-wallets.show', $customerWallet)
            ->with('success', 'Wallet unfrozen.');
    }

    public function createForUser(Request $request, User $user): RedirectResponse
    {
        $wallet = $this->walletService->resolveWallet(
            $user,
            (string) $request->input('currency', config('customer_wallets.default_currency', 'LYD')),
        );

        return redirect()
            ->route('admin.customer-wallets.show', $wallet)
            ->with('success', 'Customer wallet ready.');
    }

    public function addMoney(Request $request, User $user): RedirectResponse
    {
        $wallet = $this->walletService->resolveWallet(
            $user,
            (string) $request->input('currency', config('customer_wallets.default_currency', 'LYD')),
        );

        return redirect()->route('admin.customer-wallets.show', [
            'customerWallet' => $wallet,
            'action' => 'add-money',
        ]);
    }

    public function printTransaction(CustomerWallet $customerWallet, CustomerWalletTransaction $transaction): View
    {
        $this->ensureTransactionBelongsToWallet($customerWallet, $transaction);

        $customerWallet->loadMissing('user:id,name,full_name,email,phone');
        $transaction->loadMissing('creator:id,name,full_name,email', 'order:id,booking_reference,external_booking_id');

        return view('admin.customer-wallets.receipt-print', [
            'company' => $this->systemSettings->companyName(),
            'generated_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'wallet' => $this->serializeWallet($customerWallet),
            'transaction' => $this->serializePrintTransaction($transaction),
        ]);
    }

    public function printStatement(CustomerWallet $customerWallet): View
    {
        $customerWallet->loadMissing('user:id,name,full_name,email,phone');

        $transactions = $customerWallet->transactions()
            ->with(['creator:id,name,full_name', 'order:id,booking_reference,external_booking_id'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CustomerWalletTransaction $transaction): array => $this->serializePrintTransaction($transaction))
            ->all();

        return view('admin.customer-wallets.statement-print', [
            'company' => $this->systemSettings->companyName(),
            'generated_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'wallet' => $this->serializeWallet($customerWallet),
            'transactions' => $transactions,
        ]);
    }

    private function ensureTransactionBelongsToWallet(CustomerWallet $wallet, CustomerWalletTransaction $transaction): void
    {
        if ((int) $transaction->customer_wallet_id !== (int) $wallet->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWallet(CustomerWallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'wallet_number' => $wallet->wallet_number,
            'user_id' => $wallet->user_id,
            'user_name' => $wallet->user?->full_name ?: $wallet->user?->name,
            'user_email' => $wallet->user?->email,
            'user_phone' => $wallet->user?->phone,
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'status' => $wallet->status,
            'is_frozen' => $wallet->isFrozen(),
            'statement_print_url' => route('admin.customer-wallets.print', $wallet),
            'updated_at' => optional($wallet->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(CustomerWalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'transaction_id' => (string) $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'signed_amount' => $transaction->signedAmount(),
            'balance_before' => $transaction->balance_before,
            'balance_after' => $transaction->balance_after,
            'currency' => $transaction->currency,
            'description' => $transaction->description,
            'reason' => $transaction->reason(),
            'reason_label' => $transaction->reason()
                ? CustomerWalletTransaction::adminCreditReasonLabel($transaction->reason())
                : null,
            'note' => $transaction->note(),
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'summary' => $transaction->adminTopUpSummary(),
            'print_url' => route('admin.customer-wallets.transactions.print', [
                'customerWallet' => $transaction->customer_wallet_id,
                'transaction' => $transaction->id,
            ]),
            'order' => $transaction->order ? [
                'id' => $transaction->order->id,
                'booking_reference' => $transaction->order->booking_reference,
                'external_booking_id' => $transaction->order->external_booking_id,
            ] : null,
            'created_by' => $transaction->creator?->full_name ?: $transaction->creator?->name,
            'created_at' => optional($transaction->created_at)?->toIso8601String(),
            'metadata' => $transaction->metadata,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function creditReasons(): array
    {
        return array_map(
            fn (string $reason): array => [
                'value' => $reason,
                'label' => CustomerWalletTransaction::adminCreditReasonLabel($reason),
            ],
            CustomerWalletTransaction::adminCreditReasons(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePrintTransaction(CustomerWalletTransaction $transaction): array
    {
        $signed = $transaction->signedAmount();
        $currency = $transaction->currency;

        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'type_label' => $this->typeLabel($transaction->type),
            'type_label_ar' => $this->typeLabelAr($transaction->type),
            'amount' => number_format(abs((float) $transaction->amount), 2, '.', ''),
            'signed_amount' => $signed,
            'is_debit' => $transaction->isDebit() || (float) $signed < 0,
            'balance_before' => number_format((float) $transaction->balance_before, 2, '.', ''),
            'balance_after' => number_format((float) $transaction->balance_after, 2, '.', ''),
            'currency' => $currency,
            'reason_label' => $transaction->reason()
                ? CustomerWalletTransaction::adminCreditReasonLabel($transaction->reason())
                : null,
            'note' => $transaction->note(),
            'description' => $transaction->description,
            'summary' => $transaction->adminTopUpSummary(),
            'reference_id' => $transaction->reference_id,
            'created_by' => $transaction->creator?->full_name ?: $transaction->creator?->name,
            'created_at' => optional($transaction->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'order_reference' => $transaction->order?->booking_reference ?: $transaction->order?->external_booking_id,
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            CustomerWalletTransaction::TYPE_ADMIN_CREDIT => 'Admin top-up',
            CustomerWalletTransaction::TYPE_ADMIN_DEBIT => 'Admin debit',
            CustomerWalletTransaction::TYPE_BOOKING => 'Booking',
            CustomerWalletTransaction::TYPE_REFUND => 'Refund',
            CustomerWalletTransaction::TYPE_CREDIT => 'Credit',
            CustomerWalletTransaction::TYPE_DEBIT => 'Debit',
            CustomerWalletTransaction::TYPE_BONUS => 'Bonus',
            CustomerWalletTransaction::TYPE_ADJUSTMENT => 'Adjustment',
            default => $type,
        };
    }

    private function typeLabelAr(string $type): string
    {
        return match ($type) {
            CustomerWalletTransaction::TYPE_ADMIN_CREDIT => 'شحن من الإدارة',
            CustomerWalletTransaction::TYPE_ADMIN_DEBIT => 'خصم إداري',
            CustomerWalletTransaction::TYPE_BOOKING => 'حجز',
            CustomerWalletTransaction::TYPE_REFUND => 'استرداد',
            CustomerWalletTransaction::TYPE_CREDIT => 'إضافة',
            CustomerWalletTransaction::TYPE_DEBIT => 'خصم',
            CustomerWalletTransaction::TYPE_BONUS => 'مكافأة',
            CustomerWalletTransaction::TYPE_ADJUSTMENT => 'تسوية',
            default => $type,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWalletListItem(CustomerWallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'wallet_number' => $wallet->wallet_number,
            'user_id' => $wallet->user_id,
            'user_name' => $wallet->user?->full_name ?: $wallet->user?->name,
            'user_email' => $wallet->user?->email,
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'status' => $wallet->status,
            'is_frozen' => $wallet->isFrozen(),
            'updated_at' => optional($wallet->updated_at)?->toIso8601String(),
        ];
    }
}
