<?php

namespace App\Modules\Admin\CustomerWallets\Http\Controllers;

use App\Models\CustomerWallet;
use App\Models\User;
use App\Modules\Admin\CustomerWallets\Http\Requests\CreditCustomerWalletRequest;
use App\Modules\Admin\CustomerWallets\Http\Requests\DebitCustomerWalletRequest;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerWalletController
{
    public function __construct(
        private readonly CustomerWalletService $walletService,
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
            ->through(fn ($transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'signed_amount' => $transaction->signedAmount(),
                'balance_before' => $transaction->balance_before,
                'balance_after' => $transaction->balance_after,
                'currency' => $transaction->currency,
                'description' => $transaction->description,
                'reference_type' => $transaction->reference_type,
                'reference_id' => $transaction->reference_id,
                'order' => $transaction->order ? [
                    'id' => $transaction->order->id,
                    'booking_reference' => $transaction->order->booking_reference,
                    'external_booking_id' => $transaction->order->external_booking_id,
                ] : null,
                'created_by' => $transaction->creator?->full_name ?: $transaction->creator?->name,
                'created_at' => optional($transaction->created_at)?->toIso8601String(),
                'metadata' => $transaction->metadata,
            ]);

        return Inertia::render('admin/customer-wallets/pages/Show', [
            'wallet' => $this->serializeWallet($customerWallet->refresh()),
            'transactions' => $transactions,
            'can_manage' => $request->user()?->can('customer-wallets.manage') ?? false,
        ]);
    }

    public function credit(CreditCustomerWalletRequest $request, CustomerWallet $customerWallet): RedirectResponse
    {
        $data = $request->validated();

        $this->walletService->adminCredit(
            $customerWallet,
            $data['amount'],
            $request->user(),
            ['description' => $data['note'] ?? null],
        );

        return redirect()
            ->route('admin.customer-wallets.show', $customerWallet)
            ->with('success', 'Credit recorded.');
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
            'updated_at' => optional($wallet->updated_at)?->toIso8601String(),
        ];
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
