<?php

namespace App\Modules\Settlements\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\SettlementInvoiceImport;
use App\Models\SettlementItem;
use App\Models\User;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Finance\Support\FinancialContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {
    }
    /**
     * Create a settlement period and seed items from paid/costed orders + wallet debits.
     *
     * @param  array{provider_id: int, period_start: string, period_end: string, currency?: string, notes?: string|null}  $data
     */
    public function createPeriod(array $data, User $actor): Settlement
    {
        $provider = Provider::query()->findOrFail($data['provider_id']);
        $currency = strtoupper((string) ($data['currency'] ?? $provider->default_currency ?? app(\App\Modules\Settings\Services\SystemSettingsService::class)->defaultCurrency()));
        $periodStart = $data['period_start'];
        $periodEnd = $data['period_end'];

        if ($periodEnd < $periodStart) {
            throw ValidationException::withMessages([
                'period_end' => 'Period end must be on or after period start.',
            ]);
        }

        $exists = Settlement::query()
            ->where('provider_id', $provider->id)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->where('currency', $currency)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period_start' => 'A settlement already exists for this provider, period, and currency.',
            ]);
        }

        return DB::transaction(function () use ($provider, $periodStart, $periodEnd, $currency, $data, $actor): Settlement {
            $settlement = Settlement::query()->create([
                'provider_id' => $provider->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'currency' => $currency,
                'status' => Settlement::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->seedItemsFromOrders($settlement);
            $this->recalculateTotals($settlement);
            $this->syncWorkflowStatus($settlement->fresh());

            $settlement = $settlement->refresh()->load('provider');

            $this->auditRecorder->success(
                AuditLog::MODULE_SETTLEMENTS,
                'settlement.created',
                'Settlement period #'.$settlement->id.' created',
                AuditLog::ENTITY_SETTLEMENT,
                $settlement->id,
                $actor,
                null,
                [
                    'provider_id' => $settlement->provider_id,
                    'period_start' => $settlement->period_start?->toDateString(),
                    'period_end' => $settlement->period_end?->toDateString(),
                    'currency' => $settlement->currency,
                ],
            );

            return $settlement;
        });
    }

    /**
     * Import supplier invoice lines as a numbered import and re-run comparison.
     *
     * Re-import replaces the active invoice application. It does not duplicate items.
     *
     * @param  array<int, array{booking_reference?: string|null, order_id?: int|null, amount: float|string}>  $lines
     */
    public function importInvoice(
        Settlement $settlement,
        array $lines,
        ?User $actor = null,
        ?SettlementAttachment $attachment = null,
    ): Settlement {
        $this->assertCanMutate($settlement);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one invoice line is required.',
            ]);
        }

        $prepared = $this->prepareInvoiceLines($lines);

        if ($prepared['accepted'] === []) {
            throw ValidationException::withMessages([
                'lines' => $this->invoiceImportErrorSummary($prepared['errors']),
            ]);
        }

        return DB::transaction(function () use ($settlement, $prepared, $actor, $attachment): Settlement {
            $sequence = (int) $settlement->invoiceImports()->max('sequence') + 1;

            $import = SettlementInvoiceImport::query()->create([
                'settlement_id' => $settlement->id,
                'sequence' => $sequence,
                'attachment_id' => $attachment?->id,
                'original_name' => $attachment?->original_name,
                'uploaded_by' => $actor?->id,
                'uploaded_at' => now(),
                'row_count' => $prepared['row_count'],
                'matched_count' => 0,
                'extra_count' => 0,
                'error_count' => count($prepared['errors']),
                'errors' => $prepared['errors'] === [] ? null : $prepared['errors'],
                'is_active' => false,
            ]);

            $applied = $this->applyInvoiceImport($settlement->fresh(), $import, $prepared['accepted']);

            $import->forceFill([
                'matched_count' => $applied['matched_count'],
                'extra_count' => $applied['extra_count'],
                'error_count' => $import->error_count + $applied['error_count'],
                'errors' => $this->mergeImportErrors($import->errors ?? [], $applied['errors']),
            ])->save();

            $settlement->invoiceImports()->update(['is_active' => false]);
            $import->forceFill(['is_active' => true])->save();
            $settlement->forceFill([
                'current_invoice_import_id' => $import->id,
            ])->save();

            $this->auditRecorder->success(
                AuditLog::MODULE_SETTLEMENTS,
                'settlement.invoice_imported',
                'Imported invoice #'.$sequence.' for settlement #'.$settlement->id,
                AuditLog::ENTITY_SETTLEMENT,
                $settlement->id,
                $actor,
                null,
                [
                    'import_id' => $import->id,
                    'sequence' => $sequence,
                    'file' => $import->original_name,
                    'row_count' => $import->row_count,
                    'matched_count' => $import->matched_count,
                    'extra_count' => $import->extra_count,
                    'error_count' => $import->error_count,
                ],
            );

            $this->compare($settlement->fresh(), $actor);

            return $settlement->fresh(['provider', 'items', 'currentInvoiceImport']);
        });
    }

    /**
     * Classify every settlement item and refresh period totals.
     */
    public function compare(Settlement $settlement, ?User $actor = null): Settlement
    {
        $this->assertCanMutate($settlement);

        $tolerance = (float) config('settlements.cost_tolerance', 0.01);

        DB::transaction(function () use ($settlement, $tolerance, $actor): void {
            $settlement->items()->each(function (SettlementItem $item) use ($tolerance): void {
                if ($item->status === SettlementItem::STATUS_RESOLVED) {
                    return;
                }

                $supplierCost = $item->supplier_cost !== null ? (float) $item->supplier_cost : null;
                $invoiceCost = $item->supplier_invoice_cost !== null ? (float) $item->supplier_invoice_cost : null;
                $walletDebit = $item->wallet_debit !== null ? (float) $item->wallet_debit : null;

                if ($supplierCost === null && $invoiceCost !== null) {
                    $item->forceFill([
                        'status' => SettlementItem::STATUS_EXTRA,
                        'difference' => number_format($invoiceCost, 2, '.', ''),
                    ])->save();

                    return;
                }

                if ($supplierCost !== null && $invoiceCost === null) {
                    $item->forceFill([
                        'status' => SettlementItem::STATUS_MISSING,
                        'difference' => number_format(-$supplierCost, 2, '.', ''),
                    ])->save();

                    return;
                }

                if ($supplierCost !== null && $invoiceCost !== null) {
                    $diff = round($invoiceCost - $supplierCost, 2);

                    if (abs($diff) <= $tolerance) {
                        $walletAligned = $walletDebit === null || abs($walletDebit - $supplierCost) <= $tolerance;

                        $item->forceFill([
                            'status' => $walletAligned ? SettlementItem::STATUS_MATCHED : SettlementItem::STATUS_DIFFERENT_COST,
                            'difference' => number_format($diff, 2, '.', ''),
                        ])->save();

                        return;
                    }

                    $item->forceFill([
                        'status' => SettlementItem::STATUS_DIFFERENT_COST,
                        'difference' => number_format($diff, 2, '.', ''),
                    ])->save();
                }
            });

            $settlement->forceFill([
                'compared_at' => now(),
            ])->save();

            $this->recalculateTotals($settlement->fresh());
            $this->syncWorkflowStatus($settlement->fresh());
        });

        $fresh = $settlement->fresh(['provider', 'items']);

        $this->auditRecorder->success(
            AuditLog::MODULE_SETTLEMENTS,
            'settlement.compared',
            'Re-compared settlement #'.$settlement->id,
            AuditLog::ENTITY_SETTLEMENT,
            $settlement->id,
            $actor,
            null,
            [
                'expected_total' => (string) $fresh->expected_cost,
                'wallet_total' => (string) $fresh->wallet_debit_total,
                'invoice_total' => (string) $fresh->supplier_invoice_total,
                'variance_total' => (string) $fresh->difference,
                'matched_count' => $fresh->matched_count,
                'resolved_count' => $fresh->resolved_count,
                'review_count' => $fresh->review_count,
            ],
        );

        return $fresh;
    }

    public function approvePeriod(Settlement $settlement, User $actor): Settlement
    {
        $this->recalculateTotals($settlement->fresh());
        $settlement = $settlement->fresh();

        if ($settlement->status !== Settlement::STATUS_OPEN) {
            throw ValidationException::withMessages([
                'settlement' => 'Only open settlements with no remaining review items can be approved.',
            ]);
        }

        $this->assertReadyToLock($settlement);

        $previous = $settlement->status;

        $settlement->forceFill([
            'status' => Settlement::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_SETTLEMENTS,
            'settlement.approved',
            'Settlement #'.$settlement->id.' approved',
            AuditLog::ENTITY_SETTLEMENT,
            $settlement->id,
            $actor,
            ['status' => $previous],
            ['status' => Settlement::STATUS_APPROVED],
        );

        return $settlement->refresh();
    }

    public function closePeriod(Settlement $settlement, User $actor): Settlement
    {
        if ($settlement->isClosed()) {
            throw ValidationException::withMessages([
                'settlement' => 'Settlement is already closed.',
            ]);
        }

        if (! in_array($settlement->status, Settlement::closeableStatuses(), true)) {
            throw ValidationException::withMessages([
                'settlement' => 'Settlement can only be closed from open or approved status.',
            ]);
        }

        $this->assertReadyToLock($settlement);

        $this->recalculateTotals($settlement->fresh());
        $settlement = $settlement->fresh();
        $snapshot = $this->buildCloseSnapshot($settlement);

        $previousStatus = $settlement->status;
        $history = $settlement->close_history ?? [];
        $history[] = [
            'closed_at' => now()->toIso8601String(),
            'closed_by' => $actor->id,
            'from_status' => $previousStatus,
            'snapshot' => $snapshot,
        ];

        $settlement->forceFill([
            'status' => Settlement::STATUS_CLOSED,
            'closed_by' => $actor->id,
            'closed_at' => now(),
            'close_history' => $history,
            'close_snapshot' => $snapshot,
        ])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_SETTLEMENTS,
            'settlement.closed',
            'Settlement #'.$settlement->id.' closed',
            AuditLog::ENTITY_SETTLEMENT,
            $settlement->id,
            $actor,
            ['status' => $previousStatus],
            ['status' => Settlement::STATUS_CLOSED, 'snapshot' => $snapshot],
        );

        return $settlement->refresh();
    }

    public function reopenPeriod(Settlement $settlement, User $actor, string $reason): Settlement
    {
        if (! $settlement->isClosed()) {
            throw ValidationException::withMessages([
                'settlement' => 'Only closed settlements can be reopened.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reopen reason is required.',
            ]);
        }

        $history = $settlement->close_history ?? [];
        $history[] = [
            'reopened_at' => now()->toIso8601String(),
            'reopened_by' => $actor->id,
            'reason' => $reason,
            'previous_closed_at' => $settlement->closed_at?->toIso8601String(),
            'previous_closed_by' => $settlement->closed_by,
        ];

        $previous = $settlement->status;

        $settlement->forceFill([
            'status' => Settlement::STATUS_REOPENED,
            'reopened_by' => $actor->id,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
            'close_history' => $history,
        ])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_SETTLEMENTS,
            'settlement.reopened',
            'Settlement #'.$settlement->id.' reopened',
            AuditLog::ENTITY_SETTLEMENT,
            $settlement->id,
            $actor,
            ['status' => $previous, 'close_snapshot' => $settlement->close_snapshot],
            ['status' => Settlement::STATUS_REOPENED, 'reason' => $reason],
        );

        return $settlement->refresh();
    }

    public function recalculateTotals(Settlement $settlement): Settlement
    {
        $items = $settlement->items()->get();

        $expected = round((float) $items->sum(fn (SettlementItem $item): float => (float) ($item->supplier_cost ?? 0)), 2);
        $wallet = round((float) $items->sum(fn (SettlementItem $item): float => (float) ($item->wallet_debit ?? 0)), 2);
        $invoice = round((float) $items->sum(fn (SettlementItem $item): float => (float) ($item->supplier_invoice_cost ?? 0)), 2);
        $ordersCount = $items->whereNotNull('order_id')->count();
        $matched = $items->where('status', SettlementItem::STATUS_MATCHED)->count();
        $resolved = $items->where('status', SettlementItem::STATUS_RESOLVED)->count();
        $review = $items->filter(fn (SettlementItem $item): bool => $item->needsReview())->count();
        $adjustment = round((float) $items
            ->where('resolution_type', FinancialContract::RESOLUTION_ACCEPT_VARIANCE)
            ->whereNotNull('financial_transaction_id')
            ->sum(fn (SettlementItem $item): float => (float) ($item->resolution_amount ?? 0)), 2);

        $settlement->forceFill([
            'expected_cost' => number_format($expected, 2, '.', ''),
            'wallet_debit_total' => number_format($wallet, 2, '.', ''),
            'supplier_invoice_total' => number_format($invoice, 2, '.', ''),
            'difference' => number_format(round($invoice - $expected, 2), 2, '.', ''),
            'adjustment_total' => number_format($adjustment, 2, '.', ''),
            'orders_count' => $ordersCount,
            'matched_count' => $matched,
            'resolved_count' => $resolved,
            'review_count' => $review,
        ])->save();

        return $settlement->refresh();
    }

    private function seedItemsFromOrders(Settlement $settlement): void
    {
        $orders = Order::query()
            ->where('provider_id', $settlement->provider_id)
            ->where('currency', $settlement->currency)
            ->whereDate('created_at', '>=', $settlement->period_start)
            ->whereDate('created_at', '<=', $settlement->period_end)
            ->whereNotNull('supplier_cost')
            ->orderBy('id')
            ->get();

        $walletDebits = $this->walletDebitsByOrderId($settlement, $orders);

        foreach ($orders as $order) {
            $cost = (float) ($order->supplier_cost ?? 0);
            $debit = $walletDebits->get($order->id);

            SettlementItem::query()->create([
                'settlement_id' => $settlement->id,
                'order_id' => $order->id,
                'booking_reference' => $order->booking_reference,
                'external_booking_id' => $order->external_booking_id,
                'supplier_cost' => number_format($cost, 2, '.', ''),
                'expected_cost_source' => SettlementItem::COST_SOURCE_ORDER,
                'wallet_debit' => $debit !== null ? number_format((float) $debit, 2, '.', '') : null,
                'supplier_invoice_cost' => null,
                'difference' => number_format(-$cost, 2, '.', ''),
                'status' => SettlementItem::STATUS_MISSING,
            ]);
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, string|float>
     */
    private function walletDebitsByOrderId(Settlement $settlement, Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        $walletIds = ProviderWallet::query()
            ->where('provider_id', $settlement->provider_id)
            ->where('currency', $settlement->currency)
            ->pluck('id');

        if ($walletIds->isEmpty()) {
            return collect();
        }

        return ProviderWalletTransaction::query()
            ->whereIn('provider_wallet_id', $walletIds)
            ->where('type', ProviderWalletTransaction::TYPE_DEBIT)
            ->whereIn('order_id', $orders->pluck('id'))
            ->get()
            ->groupBy('order_id')
            ->map(fn (Collection $rows): float => round((float) $rows->sum('amount'), 2));
    }

    public function syncWorkflowStatus(Settlement $settlement): Settlement
    {
        if ($settlement->isClosed() || $settlement->isApproved() || $settlement->isReopened()) {
            return $settlement;
        }

        $this->recalculateTotals($settlement->fresh());
        $settlement = $settlement->fresh();

        $next = Settlement::STATUS_DRAFT;

        if ($settlement->compared_at) {
            $next = $settlement->review_count > 0
                ? Settlement::STATUS_PENDING_REVIEW
                : Settlement::STATUS_OPEN;
        }

        if ($settlement->status !== $next) {
            $settlement->forceFill([
                'status' => $next,
            ])->save();
        }

        return $settlement->refresh();
    }

    public function pendingAdjustmentApprovalsCount(Settlement $settlement): int
    {
        return (int) $settlement->items()->whereNotNull('pending_approval_id')->count();
    }

    /**
     * @return array{
     *     expected_total: string,
     *     wallet_total: string,
     *     invoice_total: string,
     *     variance_total: string,
     *     matched_count: int,
     *     resolved_count: int,
     *     adjustment_total: string,
     *     orders_count: int,
     *     review_count: int,
     *     captured_at: string
     * }
     */
    public function buildCloseSnapshot(Settlement $settlement): array
    {
        return [
            'expected_total' => (string) $settlement->expected_cost,
            'wallet_total' => (string) $settlement->wallet_debit_total,
            'invoice_total' => (string) $settlement->supplier_invoice_total,
            'variance_total' => (string) $settlement->difference,
            'matched_count' => (int) $settlement->matched_count,
            'resolved_count' => (int) $settlement->resolved_count,
            'adjustment_total' => (string) $settlement->adjustment_total,
            'orders_count' => (int) $settlement->orders_count,
            'review_count' => (int) $settlement->review_count,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{booking_reference?: string|null, order_id?: int|null, amount: float|string}>  $lines
     * @return array{row_count: int, accepted: list<array{index: int, booking_reference: string, order_id: int|null, amount: float}>, errors: list<array<string, mixed>>}
     */
    public function prepareInvoiceLines(array $lines): array
    {
        $normalized = [];
        $errors = [];

        foreach (array_values($lines) as $index => $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $orderId = isset($line['order_id']) && $line['order_id'] !== '' && $line['order_id'] !== null
                ? (int) $line['order_id']
                : null;
            $reference = strtoupper(trim((string) ($line['booking_reference'] ?? '')));
            $row = $index + 1;

            if ($amount < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Invoice amount cannot be negative.',
                ]);
            }

            if ($amount == 0.0) {
                $errors[] = [
                    'row' => $row,
                    'booking_reference' => $reference !== '' ? $reference : null,
                    'amount' => $amount,
                    'message' => 'Invoice amount cannot be zero.',
                ];

                continue;
            }

            if ($reference === '' && ! $orderId) {
                $errors[] = [
                    'row' => $row,
                    'booking_reference' => null,
                    'amount' => $amount,
                    'message' => 'Invoice line needs a booking_reference or order_id.',
                ];

                continue;
            }

            $key = $orderId ? 'order:'.$orderId : 'ref:'.$reference;

            $normalized[] = [
                'index' => $index,
                'row' => $row,
                'key' => $key,
                'booking_reference' => $reference,
                'order_id' => $orderId,
                'amount' => $amount,
            ];
        }

        $grouped = collect($normalized)->groupBy('key');
        $accepted = [];

        foreach ($grouped as $group) {
            if ($group->count() > 1) {
                $amounts = $group->pluck('amount')->unique()->values();
                $message = $amounts->count() > 1
                    ? 'Duplicate booking_reference with different amounts.'
                    : 'Duplicate booking_reference in the same invoice.';

                foreach ($group as $line) {
                    $errors[] = [
                        'row' => $line['row'],
                        'booking_reference' => $line['booking_reference'] !== '' ? $line['booking_reference'] : null,
                        'amount' => $line['amount'],
                        'message' => $message,
                    ];
                }

                continue;
            }

            $line = $group->first();
            $accepted[] = [
                'index' => $line['index'],
                'booking_reference' => $line['booking_reference'],
                'order_id' => $line['order_id'],
                'amount' => $line['amount'],
            ];
        }

        return [
            'row_count' => count($lines),
            'accepted' => $accepted,
            'errors' => $errors,
        ];
    }

    private function assertCanMutate(Settlement $settlement): void
    {
        if (! $settlement->canMutate()) {
            throw ValidationException::withMessages([
                'settlement' => 'Closed or approved settlements cannot be modified.',
            ]);
        }
    }

    private function assertReadyToLock(Settlement $settlement): void
    {
        $openReview = $settlement->items()
            ->whereIn('status', [
                SettlementItem::STATUS_MISSING,
                SettlementItem::STATUS_EXTRA,
                SettlementItem::STATUS_DIFFERENT_COST,
            ])
            ->count();

        if ($openReview > 0) {
            throw ValidationException::withMessages([
                'settlement' => "Cannot lock while {$openReview} item(s) still need review.",
            ]);
        }

        $pending = $this->pendingAdjustmentApprovalsCount($settlement);

        if ($pending > 0) {
            throw ValidationException::withMessages([
                'settlement' => "Cannot lock while {$pending} adjustment approval(s) are pending.",
            ]);
        }

        $hasInvoiceEvidence = $settlement->attachments()->exists()
            || $settlement->items()->whereNotNull('supplier_invoice_cost')->exists();

        if ($settlement->compared_at === null || ! $hasInvoiceEvidence) {
            throw ValidationException::withMessages([
                'settlement' => 'Cannot lock until an invoice import or attachment exists and comparison has run.',
            ]);
        }
    }

    /**
     * @param  list<array{index: int, booking_reference: string, order_id: int|null, amount: float}>  $lines
     * @return array{matched_count: int, extra_count: int, error_count: int, errors: list<array<string, mixed>>}
     */
    private function applyInvoiceImport(Settlement $settlement, SettlementInvoiceImport $import, array $lines): array
    {
        $incomingKeys = [];

        foreach ($lines as $line) {
            $incomingKeys[] = $line['order_id']
                ? 'order:'.$line['order_id']
                : 'ref:'.$line['booking_reference'];
        }

        $settlement->items()
            ->whereNull('order_id')
            ->where('status', '!=', SettlementItem::STATUS_RESOLVED)
            ->whereNull('pending_approval_id')
            ->get()
            ->each(function (SettlementItem $item) use ($incomingKeys): void {
                $key = 'ref:'.strtoupper(trim((string) $item->booking_reference));

                if (! in_array($key, $incomingKeys, true)) {
                    $item->delete();
                }
            });

        $settlement->items()
            ->whereNotNull('order_id')
            ->where('status', '!=', SettlementItem::STATUS_RESOLVED)
            ->whereNull('pending_approval_id')
            ->update([
                'supplier_invoice_cost' => null,
                'invoice_import_id' => null,
            ]);

        $matched = 0;
        $extra = 0;
        $errors = [];

        foreach ($lines as $line) {
            $amount = $line['amount'];
            $formatted = number_format($amount, 2, '.', '');
            $item = $this->findItemForInvoiceLine($settlement, $line['order_id'], $line['booking_reference']);

            if ($item && ($item->status === SettlementItem::STATUS_RESOLVED || $item->hasPendingApproval())) {
                $current = $item->supplier_invoice_cost !== null
                    ? number_format((float) $item->supplier_invoice_cost, 2, '.', '')
                    : null;

                if ($current === $formatted) {
                    $item->forceFill([
                        'invoice_import_id' => $import->id,
                    ])->save();

                    if ($item->order_id) {
                        $matched++;
                    } else {
                        $extra++;
                    }

                    continue;
                }

                $errors[] = [
                    'row' => $line['index'] + 1,
                    'booking_reference' => $line['booking_reference'] !== '' ? $line['booking_reference'] : null,
                    'amount' => $amount,
                    'message' => 'Invoice line conflicts with a resolved or pending-approval item.',
                ];

                continue;
            }

            if ($item) {
                $item->forceFill([
                    'supplier_invoice_cost' => $formatted,
                    'booking_reference' => $item->booking_reference ?: ($line['booking_reference'] !== '' ? $line['booking_reference'] : $item->booking_reference),
                    'invoice_import_id' => $import->id,
                ])->save();

                if ($item->order_id) {
                    $matched++;
                } else {
                    $extra++;
                }

                continue;
            }

            SettlementItem::query()->create([
                'settlement_id' => $settlement->id,
                'order_id' => null,
                'booking_reference' => $line['booking_reference'] !== '' ? $line['booking_reference'] : null,
                'supplier_cost' => null,
                'expected_cost_source' => SettlementItem::COST_SOURCE_ORDER,
                'wallet_debit' => null,
                'supplier_invoice_cost' => $formatted,
                'difference' => $formatted,
                'status' => SettlementItem::STATUS_EXTRA,
                'invoice_import_id' => $import->id,
                'metadata' => ['source' => 'invoice_import'],
            ]);

            $extra++;
        }

        return [
            'matched_count' => $matched,
            'extra_count' => $extra,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $existing
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>|null
     */
    private function mergeImportErrors(?array $existing, array $extra): ?array
    {
        $merged = array_values(array_merge($existing ?? [], $extra));

        return $merged === [] ? null : $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    private function invoiceImportErrorSummary(array $errors): string
    {
        $first = $errors[0]['message'] ?? 'Invoice import has invalid rows.';

        return $first.(count($errors) > 1 ? ' ('.count($errors).' invalid rows)' : '');
    }

    private function findItemForInvoiceLine(Settlement $settlement, ?int $orderId, string $reference): ?SettlementItem
    {
        if ($orderId) {
            $byOrder = $settlement->items()->where('order_id', $orderId)->first();

            if ($byOrder) {
                return $byOrder;
            }
        }

        if ($reference !== '') {
            $needle = strtoupper($reference);

            $byOrderReference = $settlement->items()
                ->whereNotNull('order_id')
                ->where(function ($query) use ($needle): void {
                    $query->whereRaw('UPPER(booking_reference) = ?', [$needle])
                        ->orWhereRaw('UPPER(external_booking_id) = ?', [$needle]);
                })
                ->first();

            if ($byOrderReference) {
                return $byOrderReference;
            }

            return $settlement->items()
                ->whereNull('order_id')
                ->whereRaw('UPPER(booking_reference) = ?', [$needle])
                ->first();
        }

        return null;
    }
}
