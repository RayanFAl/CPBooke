<?php

namespace App\Modules\Admin\Settlements\Http\Controllers;

use App\Models\Provider;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\SettlementInvoiceImport;
use App\Models\SettlementItem;
use App\Modules\Admin\Settlements\Http\Requests\ImportSettlementInvoiceRequest;
use App\Modules\Admin\Settlements\Http\Requests\ReopenSettlementRequest;
use App\Modules\Admin\Settlements\Http\Requests\ResolveSettlementItemRequest;
use App\Modules\Admin\Settlements\Http\Requests\StoreSettlementAttachmentRequest;
use App\Modules\Admin\Settlements\Http\Requests\StoreSettlementRequest;
use App\Modules\Audit\Services\EntityTimelineService;
use App\Modules\Finance\Support\FinancialContract;
use App\Modules\Settlements\Services\SettlementAttachmentService;
use App\Modules\Settlements\Services\SettlementResolutionService;
use App\Modules\Settlements\Services\SettlementService;
use App\Modules\Settlements\Support\SettlementInvoiceParser;
use App\Modules\Settings\Services\SystemSettingsService;
use App\Modules\Wallets\Services\ProviderWalletBalanceQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController
{
    public function __construct(
        private readonly SettlementService $settlementService,
        private readonly SettlementResolutionService $settlementResolutionService,
        private readonly SettlementAttachmentService $settlementAttachmentService,
        private readonly SettlementInvoiceParser $settlementInvoiceParser,
        private readonly EntityTimelineService $entityTimelineService,
        private readonly SystemSettingsService $systemSettingsService,
        private readonly ProviderWalletBalanceQueryService $providerWalletBalanceQuery,
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
            'default_currency' => $this->systemSettingsService->defaultCurrency(),
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
            'approver:id,name,full_name',
            'reopener:id,name,full_name',
            'attachments.uploader:id,name,full_name',
            'invoiceImports.uploader:id,name,full_name',
            'currentInvoiceImport',
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

        $provider = $settlement->provider;

        return Inertia::render('admin/settlements/pages/Show', [
            'settlement' => array_merge($this->serializeSettlement($settlement, detailed: true), [
                'print_url' => route('admin.settlements.print', $settlement, absolute: false),
                'export_csv_url' => route('admin.settlements.export.csv', $settlement, absolute: false),
            ]),
            'provider_api_wallets' => $provider
                ? $this->providerWalletBalanceQuery->fetchForProvider($provider)
                : [
                    'available' => false,
                    'error' => null,
                    'wallet_count' => 0,
                    'wallets' => [],
                    'fetched_at' => null,
                ],
            'items' => $items,
            'attachments' => $settlement->attachments
                ->map(fn (SettlementAttachment $attachment): array => $this->serializeAttachment($attachment))
                ->values(),
            'invoice_imports' => $settlement->invoiceImports
                ->sortByDesc('sequence')
                ->values()
                ->map(fn ($import): array => $this->serializeInvoiceImport($import))
                ->values(),
            'system_timeline' => $this->entityTimelineService->forSettlement($settlement),
            'filters' => [
                'item_status' => $itemStatus,
            ],
            'can_manage' => $request->user()?->can('settlements.manage') ?? false,
            'can_reopen' => $request->user()?->can('settlements.reopen') ?? false,
            'resolution_reasons' => FinancialContract::adjustmentReasons(),
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
            $csvText = $request->string('csv_text')->value();
            $lines = $this->settlementInvoiceParser->parseCsvText($csvText);
        }

        if ($request->hasFile('invoice_file')) {
            $lines = $this->settlementInvoiceParser->parseUploadedFile($request->file('invoice_file'));
        }

        $prepared = $this->settlementService->prepareInvoiceLines($lines);

        if ($prepared['accepted'] === []) {
            return redirect()
                ->route('admin.settlements.show', $settlement)
                ->withErrors(['lines' => $prepared['errors'][0]['message'] ?? 'Invoice import has invalid rows.']);
        }

        $attachment = null;

        if ($request->filled('csv_text') && ! $request->hasFile('invoice_file')) {
            $attachment = $this->settlementAttachmentService->storePastedCsv(
                $settlement,
                $request->string('csv_text')->value(),
                $request->user(),
            );
        }

        if ($request->hasFile('invoice_file')) {
            $file = $request->file('invoice_file');
            $attachment = $this->settlementAttachmentService->storeUploadedFile(
                $settlement,
                $file,
                $request->user(),
                $this->settlementInvoiceParser->kindForFile($file),
            );
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $this->settlementAttachmentService->storeUploadedFile(
                $settlement,
                $file,
                $request->user(),
                $this->settlementInvoiceParser->kindForFile($file),
            );
        }

        $this->settlementService->importInvoice($settlement, $lines, $request->user(), $attachment);

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Invoice imported and comparison refreshed.');
    }

    public function storeAttachment(StoreSettlementAttachmentRequest $request, Settlement $settlement): RedirectResponse
    {
        Gate::authorize('settlements.manage');

        if (! $settlement->canMutate()) {
            abort(422, 'Closed or approved settlements cannot be modified.');
        }

        $file = $request->file('file');
        $this->settlementAttachmentService->storeUploadedFile(
            $settlement,
            $file,
            $request->user(),
            $this->settlementInvoiceParser->kindForFile($file),
        );

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Attachment stored on this settlement period.');
    }

    public function downloadAttachment(Request $request, Settlement $settlement, SettlementAttachment $attachment): StreamedResponse
    {
        Gate::authorize('settlements.view');

        if ((int) $attachment->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function compare(Request $request, Settlement $settlement): RedirectResponse
    {
        Gate::authorize('settlements.manage');

        $this->settlementService->compare($settlement, $request->user());

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

        $result = $this->settlementResolutionService->resolve(
            $item,
            $request->user(),
            $request->validated(),
        );

        $message = $result['executed']
            ? 'Settlement item resolution applied.'
            : 'Adjustment submitted for approval.';

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', $message);
    }

    public function approve(Request $request, Settlement $settlement): RedirectResponse
    {
        Gate::authorize('settlements.manage');

        $this->settlementService->approvePeriod($settlement, $request->user());

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement period approved.');
    }

    public function close(Request $request, Settlement $settlement): RedirectResponse
    {
        Gate::authorize('settlements.manage');

        $this->settlementService->closePeriod($settlement, $request->user());

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement period closed.');
    }

    public function reopen(ReopenSettlementRequest $request, Settlement $settlement): RedirectResponse
    {
        $this->settlementService->reopenPeriod(
            $settlement,
            $request->user(),
            $request->string('reason')->value(),
        );

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement period reopened.');
    }

    public function printReport(Settlement $settlement): View
    {
        Gate::authorize('settlements.view');

        $settlement->load(['provider:id,name,key,settlement_cycle']);

        $items = $settlement->items()
            ->orderBy('id')
            ->get()
            ->map(fn (SettlementItem $item): array => $this->serializeItem($item))
            ->all();

        $portalWallets = $settlement->provider
            ? $this->providerWalletBalanceQuery->fetchForProvider($settlement->provider)
            : ['wallets' => []];

        return view('admin.settlements.report-print', [
            'company' => $this->systemSettingsService->companyName(),
            'generated_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'settlement' => $this->serializeSettlement($settlement, detailed: true),
            'items' => $items,
            'portal_wallets' => $portalWallets,
        ]);
    }

    public function exportCsv(Settlement $settlement): StreamedResponse
    {
        Gate::authorize('settlements.view');

        $settlement->load('provider:id,name');

        $filename = sprintf(
            'settlement-%d-%s-%s.csv',
            $settlement->id,
            $settlement->provider?->key ?? 'provider',
            optional($settlement->period_start)?->format('Y-m'),
        );

        $items = $settlement->items()->orderBy('id')->get();

        return response()->streamDownload(function () use ($settlement, $items): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'booking_reference',
                'booke_cost',
                'wallet_debit',
                'invoice_amount',
                'difference',
                'status',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->booking_reference,
                    $item->supplier_cost,
                    $item->wallet_debit,
                    $item->supplier_invoice_cost,
                    $item->difference,
                    $item->status,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
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
            'resolved_count' => $settlement->resolved_count,
            'review_count' => $settlement->review_count,
            'adjustment_total' => $settlement->adjustment_total,
            'pending_approvals' => $detailed ? $this->settlementService->pendingAdjustmentApprovalsCount($settlement) : null,
            'created_by' => $settlement->creator?->full_name ?: $settlement->creator?->name,
            'created_at' => optional($settlement->created_at)?->toIso8601String(),
        ];

        if ($detailed) {
            $data['notes'] = $settlement->notes;
            $data['settlement_cycle'] = $settlement->provider?->settlement_cycle;
            $data['compared_at'] = optional($settlement->compared_at)?->toIso8601String();
            $data['approved_at'] = optional($settlement->approved_at)?->toIso8601String();
            $data['approved_by'] = $settlement->approver?->full_name ?: $settlement->approver?->name;
            $data['closed_at'] = optional($settlement->closed_at)?->toIso8601String();
            $data['closed_by'] = $settlement->closer?->full_name ?: $settlement->closer?->name;
            $data['reopened_at'] = optional($settlement->reopened_at)?->toIso8601String();
            $data['reopened_by'] = $settlement->reopener?->full_name ?: $settlement->reopener?->name;
            $data['reopen_reason'] = $settlement->reopen_reason;
            $data['close_history'] = $settlement->close_history ?? [];
            $data['close_snapshot'] = $settlement->close_snapshot;
            $data['current_invoice_import_id'] = $settlement->current_invoice_import_id;
            $data['can_mutate'] = $settlement->canMutate();
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
            'expected_cost_source' => $item->expected_cost_source,
            'wallet_debit' => $item->wallet_debit,
            'supplier_invoice_cost' => $item->supplier_invoice_cost,
            'difference' => $item->difference,
            'status' => $item->status,
            'resolution_type' => $item->resolution_type,
            'resolution_reason' => $item->resolution_reason,
            'resolution_amount' => $item->resolution_amount,
            'resolution_note' => $item->resolution_note,
            'pending_approval_id' => $item->pending_approval_id,
            'financial_transaction_id' => $item->financial_transaction_id,
            'needs_review' => $item->needsReview(),
            'order_status' => $item->order?->status,
            'payment_status' => $item->order?->payment_status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttachment(SettlementAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind,
            'original_name' => $attachment->original_name,
            'mime' => $attachment->mime,
            'size' => $attachment->size,
            'source' => $attachment->source,
            'uploaded_by' => $attachment->uploader?->full_name ?: $attachment->uploader?->name,
            'created_at' => optional($attachment->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceImport(SettlementInvoiceImport $import): array
    {
        return [
            'id' => $import->id,
            'sequence' => $import->sequence,
            'original_name' => $import->original_name,
            'uploaded_by' => $import->uploader?->full_name ?: $import->uploader?->name,
            'uploaded_at' => optional($import->uploaded_at)?->toIso8601String(),
            'row_count' => $import->row_count,
            'matched_count' => $import->matched_count,
            'extra_count' => $import->extra_count,
            'error_count' => $import->error_count,
            'errors' => $import->errors ?? [],
            'is_active' => (bool) $import->is_active,
        ];
    }
}
