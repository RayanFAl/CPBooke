<?php

namespace App\Modules\Admin\Settlements\Http\Controllers;

use App\Models\Provider;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Modules\Admin\Settlements\Http\Requests\ImportSettlementInvoiceRequest;
use App\Modules\Admin\Settlements\Http\Requests\ResolveSettlementItemRequest;
use App\Modules\Admin\Settlements\Http\Requests\StoreSettlementRequest;
use App\Modules\Audit\Services\EntityTimelineService;
use App\Modules\Settlements\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettlementController
{
    public function __construct(
        private readonly SettlementService $settlementService,
        private readonly EntityTimelineService $entityTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('settlements.view');

        $status = trim((string) $request->input('status', ''));
        $providerId = $request->integer('provider_id') ?: null;

        $settlements = Settlement::query()
            ->with(['provider:id,name,key', 'creator:id,name,full_name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($providerId, fn ($query) => $query->where('provider_id', $providerId))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Settlement $settlement): array => $this->serializeSettlement($settlement));

        return Inertia::render('admin/settlements/pages/Index', [
            'settlements' => $settlements,
            'filters' => [
                'status' => $status,
                'provider_id' => $providerId,
            ],
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name', 'key']),
            'can_manage' => $request->user()?->can('settlements.manage') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('settlements.manage');

        return Inertia::render('admin/settlements/pages/Create', [
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name', 'key', 'default_currency']),
            'default_currency' => \App\Support\Platform\PlatformSettings::defaultCurrency(
                (string) config('settlements.default_currency', 'LYD')
            ),
        ]);
    }

    public function store(StoreSettlementRequest $request): RedirectResponse
    {
        $settlement = $this->settlementService->createPeriod($request->validated(), $request->user());

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement period created with '.$settlement->orders_count.' order(s).');
    }

    public function show(Request $request, Settlement $settlement): Response
    {
        Gate::authorize('settlements.view');

        $settlement->load([
            'provider:id,name,key,settlement_cycle',
            'creator:id,name,full_name',
            'closer:id,name,full_name',
        ]);

        $itemStatus = trim((string) $request->input('item_status', ''));

        $items = $settlement->items()
            ->with(['order:id,booking_reference,external_booking_id,status,payment_status'])
            ->when($itemStatus !== '', fn ($query) => $query->where('status', $itemStatus))
            ->orderByRaw("CASE status
                WHEN 'different_cost' THEN 1
                WHEN 'missing' THEN 2
                WHEN 'extra' THEN 3
                WHEN 'resolved' THEN 4
                ELSE 5 END")
            ->orderBy('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (SettlementItem $item): array => $this->serializeItem($item));

        return Inertia::render('admin/settlements/pages/Show', [
            'settlement' => $this->serializeSettlement($settlement, detailed: true),
            'items' => $items,
            'system_timeline' => $this->entityTimelineService->forSettlement($settlement),
            'filters' => [
                'item_status' => $itemStatus,
            ],
            'can_manage' => $request->user()?->can('settlements.manage') ?? false,
            'item_statuses' => [
                SettlementItem::STATUS_MATCHED,
                SettlementItem::STATUS_MISSING,
                SettlementItem::STATUS_EXTRA,
                SettlementItem::STATUS_DIFFERENT_COST,
                SettlementItem::STATUS_RESOLVED,
            ],
        ]);
    }

    public function importInvoice(ImportSettlementInvoiceRequest $request, Settlement $settlement): RedirectResponse
    {
        $lines = array_values(array_filter(
            $request->input('lines', []) ?: [],
            fn ($line): bool => is_array($line) && isset($line['amount']),
        ));

        if ($lines === [] && $request->filled('csv_text')) {
            $lines = $this->parseCsvText($request->string('csv_text')->value());
        }

        $this->settlementService->importInvoice($settlement, $lines);

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Invoice imported and comparison refreshed.');
    }

    public function compare(Request $request, Settlement $settlement): RedirectResponse
    {
        Gate::authorize('settlements.manage');

        $this->settlementService->compare($settlement);

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement comparison completed.');
    }

    public function resolveItem(
        ResolveSettlementItemRequest $request,
        Settlement $settlement,
        SettlementItem $item,
    ): RedirectResponse {
        if ((int) $item->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $this->settlementService->resolveItem(
            $item,
            $request->user(),
            $request->string('resolution_note')->value(),
        );

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement item resolved.');
    }

    public function close(Request $request, Settlement $settlement): RedirectResponse
    {
        Gate::authorize('settlements.manage');

        $this->settlementService->closePeriod($settlement, $request->user());

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement period closed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlement(Settlement $settlement, bool $detailed = false): array
    {
        $data = [
            'id' => $settlement->id,
            'provider_id' => $settlement->provider_id,
            'provider_name' => $settlement->provider?->name,
            'provider_key' => $settlement->provider?->key,
            'period_start' => optional($settlement->period_start)?->toDateString(),
            'period_end' => optional($settlement->period_end)?->toDateString(),
            'currency' => $settlement->currency,
            'status' => $settlement->status,
            'expected_cost' => $settlement->expected_cost,
            'wallet_debit_total' => $settlement->wallet_debit_total,
            'supplier_invoice_total' => $settlement->supplier_invoice_total,
            'difference' => $settlement->difference,
            'orders_count' => $settlement->orders_count,
            'matched_count' => $settlement->matched_count,
            'review_count' => $settlement->review_count,
            'created_by' => $settlement->creator?->full_name ?: $settlement->creator?->name,
            'created_at' => optional($settlement->created_at)?->toIso8601String(),
        ];

        if ($detailed) {
            $data['notes'] = $settlement->notes;
            $data['settlement_cycle'] = $settlement->provider?->settlement_cycle;
            $data['compared_at'] = optional($settlement->compared_at)?->toIso8601String();
            $data['closed_at'] = optional($settlement->closed_at)?->toIso8601String();
            $data['closed_by'] = $settlement->closer?->full_name ?: $settlement->closer?->name;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(SettlementItem $item): array
    {
        return [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'booking_reference' => $item->booking_reference,
            'external_booking_id' => $item->external_booking_id,
            'supplier_cost' => $item->supplier_cost,
            'wallet_debit' => $item->wallet_debit,
            'supplier_invoice_cost' => $item->supplier_invoice_cost,
            'difference' => $item->difference,
            'status' => $item->status,
            'resolution_note' => $item->resolution_note,
            'needs_review' => $item->needsReview(),
            'order_status' => $item->order?->status,
            'payment_status' => $item->order?->payment_status,
        ];
    }

    /**
     * Parse CSV: booking_reference,amount (optional header).
     *
     * @return array<int, array{booking_reference: string, amount: float}>
     */
    private function parseCsvText(string $csvText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvText)) ?: [];
        $result = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = str_getcsv($line);

            if (count($parts) < 2) {
                continue;
            }

            $ref = trim((string) $parts[0]);
            $amount = trim((string) $parts[1]);

            if ($index === 0 && ! is_numeric($amount)) {
                continue;
            }

            if (! is_numeric($amount)) {
                continue;
            }

            $result[] = [
                'booking_reference' => $ref,
                'amount' => (float) $amount,
            ];
        }

        return $result;
    }
}
