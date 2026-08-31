<?php

namespace App\Modules\Admin\ProviderWallets\Http\Controllers;

use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Modules\Admin\ProviderWallets\Http\Requests\AdjustProviderWalletRequest;
use App\Modules\Admin\ProviderWallets\Http\Requests\DepositProviderWalletRequest;
use App\Modules\Admin\ProviderWallets\Http\Requests\StoreProviderWalletRequest;
use App\Modules\Admin\ProviderWallets\Services\ProviderWalletService;
use App\Modules\Approvals\Services\SupportOrderApprovalBridge;
use App\Modules\Audit\Services\EntityTimelineService;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ProviderWalletController
{
    public function __construct(
        private readonly ProviderWalletService $walletService,
        private readonly SupportOrderApprovalBridge $supportOrderApprovalBridge,
        private readonly EntityTimelineService $entityTimelineService,
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $wallets = ProviderWallet::query()
            ->with('provider:id,name,key,status,credit_limit')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('currency', 'like', "%{$search}%")
                        ->orWhere('environment', 'like', "%{$search}%")
                        ->orWhereHas('provider', function ($providerQuery) use ($search): void {
                            $providerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('key', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProviderWallet $wallet): array => $this->serializeWallet($wallet));

        return Inertia::render('admin/provider-wallets/pages/Index', [
            'wallets' => $wallets,
            'filters' => [
                'search' => $search,
            ],
            'can_manage' => $request->user()?->can('provider-wallets.manage') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/provider-wallets/pages/Create', [
            'providers' => Provider::query()
                ->orderBy('name')
                ->get(['id', 'name', 'key', 'status', 'default_currency', 'credit_limit']),
            'environments' => config('wallets.environments', [
                ProviderWallet::ENVIRONMENT_PRODUCTION,
                ProviderWallet::ENVIRONMENT_SANDBOX,
            ]),
            'default_allow_negative' => (bool) config('wallets.default_allow_negative', true),
            'selected_provider_id' => $request->integer('provider_id') ?: null,
            'can_manage_suppliers' => $request->user()?->can('suppliers.manage') ?? false,
        ]);
    }

    public function store(StoreProviderWalletRequest $request): RedirectResponse
    {
        $wallet = $this->walletService->createWallet($request->validated());

        return redirect()
            ->route('admin.provider-wallets.show', $wallet)
            ->with('success', 'Provider wallet created.');
    }

    public function show(Request $request, ProviderWallet $providerWallet): Response
    {
        $providerWallet->loadMissing('provider:id,name,key,status,credit_limit');

        $transactions = $providerWallet->transactions()
            ->with(['order:id,booking_reference,external_booking_id', 'creator:id,name,full_name'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'signed_amount' => $transaction->signedAmount(),
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

        return Inertia::render('admin/provider-wallets/pages/Show', [
            'wallet' => $this->serializeWallet($providerWallet->refresh()),
            'transactions' => $transactions,
            'system_timeline' => $this->entityTimelineService->forProviderWallet($providerWallet),
            'can_manage' => $request->user()?->can('provider-wallets.manage') ?? false,
        ]);
    }

    public function deposit(DepositProviderWalletRequest $request, ProviderWallet $providerWallet): RedirectResponse
    {
        $result = $this->supportOrderApprovalBridge->submitWalletDeposit(
            $providerWallet,
            $request->user(),
            $request->validated(),
        );

        return $this->redirectAfterWalletAction(
            $providerWallet,
            $result,
            executedMessage: 'Deposit recorded.',
            pendingMessage: 'Deposit request submitted for approval.',
        );
    }

    public function adjust(AdjustProviderWalletRequest $request, ProviderWallet $providerWallet): RedirectResponse
    {
        $result = $this->supportOrderApprovalBridge->submitWalletAdjustment(
            $providerWallet,
            $request->user(),
            $request->validated(),
        );

        return $this->redirectAfterWalletAction(
            $providerWallet,
            $result,
            executedMessage: 'Adjustment recorded.',
            pendingMessage: 'Adjustment request submitted for approval.',
        );
    }

    public function printStatement(ProviderWallet $providerWallet): View
    {
        $providerWallet->loadMissing('provider:id,name,key,status,credit_limit');

        $transactions = $providerWallet->transactions()
            ->with(['order:id,booking_reference', 'creator:id,name,full_name'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn ($transaction): array => $this->serializePrintTransaction($transaction))
            ->all();

        return view('admin.provider-wallets.statement-print', [
            'company' => $this->systemSettingsService->companyName(),
            'generated_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'wallet' => $this->serializeWallet($providerWallet),
            'transactions' => $transactions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePrintTransaction($transaction): array
    {
        $signed = $transaction->signedAmount();

        return [
            'id' => $transaction->id,
            'type_label' => ucfirst((string) $transaction->type),
            'signed_amount' => number_format((float) $signed, 2, '.', ''),
            'is_debit' => (float) $signed < 0,
            'balance_after' => $transaction->balance_after,
            'currency' => $transaction->currency,
            'description' => $transaction->description,
            'booking_reference' => $transaction->order?->booking_reference,
            'created_by' => $transaction->creator?->full_name ?: $transaction->creator?->name,
            'created_at' => optional($transaction->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array{executed: bool, approval: \App\Models\Approval|null, result: array<string, mixed>|null}  $result
     */
    private function redirectAfterWalletAction(
        ProviderWallet $providerWallet,
        array $result,
        string $executedMessage,
        string $pendingMessage,
    ): RedirectResponse {
        $redirect = redirect()->route('admin.provider-wallets.show', $providerWallet);

        if ($result['executed']) {
            return $redirect->with('success', $executedMessage);
        }

        return $redirect->with(
            'info',
            $pendingMessage.' Approval #'.$result['approval']?->id.' is pending review.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWallet(ProviderWallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'provider_id' => $wallet->provider_id,
            'provider_key' => $wallet->provider?->key,
            'provider_name' => $wallet->provider?->name,
            'provider_status' => $wallet->provider?->status,
            'currency' => $wallet->currency,
            'environment' => $wallet->environment,
            'balance' => $wallet->balance,
            'available_balance' => $wallet->availableBalance(),
            'credit_limit' => (float) ($wallet->provider?->credit_limit ?? 0),
            'low_balance_threshold' => $wallet->low_balance_threshold,
            'allow_negative' => (bool) $wallet->allow_negative,
            'is_active' => $wallet->is_active,
            'is_low_balance' => $wallet->isLowBalance(),
            'is_negative' => (float) $wallet->balance < 0,
            'updated_at' => optional($wallet->updated_at)?->toIso8601String(),
            'statement_print_url' => route('admin.provider-wallets.print', $wallet, absolute: false),
        ];
    }
}
