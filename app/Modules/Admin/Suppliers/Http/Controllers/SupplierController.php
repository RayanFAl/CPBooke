<?php

namespace App\Modules\Admin\Suppliers\Http\Controllers;

use App\Models\Provider;
use App\Modules\Admin\Suppliers\Http\Requests\StoreSupplierRequest;
use App\Modules\Admin\Suppliers\Http\Requests\UpdateSupplierRequest;
use App\Modules\Admin\Suppliers\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        $suppliers = $this->supplierService
            ->paginate($filters)
            ->through(fn (Provider $provider): array => $this->serialize($provider));

        return Inertia::render('admin/suppliers/pages/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'can_manage' => $request->user()?->can('suppliers.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/suppliers/pages/Form', [
            'supplier' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->supplierService->create($request->validated());

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'Supplier created.');
    }

    public function show(Request $request, Provider $supplier): Response
    {
        $supplier->load(['wallets' => fn ($query) => $query->orderBy('currency')->orderBy('environment')]);

        return Inertia::render('admin/suppliers/pages/Show', [
            'supplier' => $this->serialize($supplier, true),
            'can_manage' => $request->user()?->can('suppliers.manage') ?? false,
            'can_view_wallets' => $request->user()?->can('provider-wallets.view') ?? false,
        ]);
    }

    public function edit(Provider $supplier): Response
    {
        return Inertia::render('admin/suppliers/pages/Form', [
            'supplier' => $this->serialize($supplier),
            'options' => $this->options(),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Provider $supplier): RedirectResponse
    {
        $supplier = $this->supplierService->update($supplier, $request->validated());

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'Supplier updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Provider $provider, bool $withWallets = false): array
    {
        $payload = [
            'id' => $provider->id,
            'name' => $provider->name,
            'legal_name' => $provider->legal_name,
            'key' => $provider->key,
            'status' => $provider->status,
            'commission_rate' => $provider->commission_rate,
            'settlement_cycle' => $provider->settlement_cycle,
            'credit_limit' => $provider->credit_limit,
            'default_currency' => $provider->default_currency,
            'contact_name' => $provider->contact_name,
            'contact_email' => $provider->contact_email,
            'contact_phone' => $provider->contact_phone,
            'integration_status' => $provider->integration_status,
            'contract_starts_at' => optional($provider->contract_starts_at)?->toDateString(),
            'contract_ends_at' => optional($provider->contract_ends_at)?->toDateString(),
            'contract_notes' => $provider->contract_notes,
            'notes' => $provider->notes,
            'website' => $provider->website,
            'wallets_count' => $provider->wallets_count ?? $provider->wallets()->count(),
            'updated_at' => optional($provider->updated_at)?->toIso8601String(),
        ];

        if ($withWallets) {
            $payload['wallets'] = $provider->wallets->map(fn ($wallet): array => [
                'id' => $wallet->id,
                'currency' => $wallet->currency,
                'environment' => $wallet->environment,
                'balance' => $wallet->balance,
                'allow_negative' => (bool) $wallet->allow_negative,
                'is_negative' => (float) $wallet->balance < 0,
                'is_low_balance' => $wallet->isLowBalance(),
            ])->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'statuses' => [Provider::STATUS_ACTIVE, Provider::STATUS_INACTIVE],
            'settlement_cycles' => Provider::settlementCycles(),
            'integration_statuses' => Provider::integrationStatuses(),
        ];
    }
}
