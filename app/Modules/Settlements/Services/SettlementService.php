<?php

namespace App\Modules\Settlements\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\User;
use App\Modules\Audit\Services\AuditRecorder;
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
        $currency = strtoupper((string) ($data['currency'] ?? $provider->default_currency ?? \App\Support\Platform\PlatformSettings::defaultCurrency(
            (string) config('settlements.default_currency', 'LYD')
        )));
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
     * Import supplier invoice lines and re-run comparison.
     *
     * Each line: booking_reference and/or order_id + amount.
     *
     * @param  array<int, array{booking_reference?: string|null, order_id?: int|null, amount: float|string}>  $lines
     */
    public function importInvoice(Settlement $settlement, array $lines): Settlement
    {
        $this->assertMutable($settlement);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one invoice line is required.',
            ]);
        }

        return DB::transaction(function () use ($settlement, $lines): Settlement {
            $settlement->items()
                ->whereNull('order_id')
                ->where('status', '!=', SettlementItem::STATUS_RESOLVED)
                ->delete();

            $settlement->items()->update([
                'supplier_invoice_cost' => null,
            ]);

            foreach ($lines as $index => $line) {
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $orderId = isset($line['order_id']) ? (int) $line['order_id'] : null;
                $reference = trim((string) ($line['booking_reference'] ?? ''));

                if ($amount < 0) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.amount" => 'Invoice amount cannot be negative.',
                    ]);
                }

                $item = $this->findItemForInvoiceLine($settlement, $orderId, $reference);

                if ($item) {
                    $item->forceFill([
                        'supplier_invoice_cost' => number_format($amount, 2, '.', ''),
                        'booking_reference' => $item->booking_reference ?: ($reference !== '' ? $reference : $item->booking_reference),
                    ])->save();

                    continue;
                }

                SettlementItem::query()->create([
                    'settlement_id' => $settlement->id,
                    'order_id' => null,
                    'booking_reference' => $reference !== '' ? $reference : null,
                    'supplier_cost' => null,
                    'wallet_debit' => null,
                    'supplier_invoice_cost' => number_format($amount, 2, '.', ''),
                    'difference' => number_format($amount, 2, '.', ''),
                    'status' => SettlementItem::STATUS_EXTRA,
                    'metadata' => ['source' => 'invoice_import'],
                ]);
            }

            $this->compare($settlement->fresh());

            $fresh = $settlement->fresh(['provider', 'items']);

            $this->auditRecorder->success(
                AuditLog::MODULE_SETTLEMENTS,
                'settlement.invoice_imported',
                'Invoice imported for settlement #'.$settlement->id,
                AuditLog::ENTITY_SETTLEMENT,
                $settlement->id,
                null,
                null,
                ['lines' => count($lines)],
            );

            return $fresh;
        });
    }

    /**
     * Classify every settlement item and refresh period totals.
     */
    public function compare(Settlement $settlement): Settlement
    {
        $this->assertMutable($settlement);

        $tolerance = (float) config('settlements.cost_tolerance', 0.01);

        DB::transaction(function () use ($settlement, $tolerance): void {
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
                'status' => Settlement::STATUS_OPEN,
                'compared_at' => now(),
            ])->save();

            $this->recalculateTotals($settlement->fresh());
        });

        return $settlement->fresh(['provider', 'items']);
    }

    public function resolveItem(SettlementItem $item, User $actor, string $note): SettlementItem
    {
        $settlement = $item->settlement;

        if ($settlement === null || ! $settlement->canMutate()) {
            throw ValidationException::withMessages([
                'settlement' => 'Closed settlements cannot be modified.',
            ]);
        }

        if (! $item->needsReview() && $item->status !== SettlementItem::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'item' => 'Only items that need review can be resolved.',
            ]);
        }

        $item->forceFill([
            'status' => SettlementItem::STATUS_RESOLVED,
            'resolution_note' => trim($note),
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ])->save();

        $this->recalculateTotals($settlement->fresh());

        $this->auditRecorder->success(
            AuditLog::MODULE_SETTLEMENTS,
            'settlement.item_resolved',
            'Settlement item #'.$item->id.' resolved',
            AuditLog::ENTITY_SETTLEMENT,
            $settlement->id,
            $actor,
            null,
            ['item_id' => $item->id, 'status' => SettlementItem::STATUS_RESOLVED],
            ['resolution_note' => trim($note), 'order_id' => $item->order_id],
        );

        return $item->refresh();
    }

    public function closePeriod(Settlement $settlement, User $actor): Settlement
    {
        if ($settlement->isClosed()) {
            throw ValidationException::withMessages([
                'settlement' => 'Settlement is already closed.',
            ]);
        }

        $openReview = $settlement->items()
            ->whereIn('status', [
                SettlementItem::STATUS_MISSING,
                SettlementItem::STATUS_EXTRA,
                SettlementItem::STATUS_DIFFERENT_COST,
            ])
            ->count();

        if ($openReview > 0) {
            throw ValidationException::withMessages([
                'settlement' => "Cannot close while {$openReview} item(s) still need review. Resolve them first.",
            ]);
        }

        $this->recalculateTotals($settlement);

        $previousStatus = $settlement->status;

        $settlement->forceFill([
            'status' => Settlement::STATUS_CLOSED,
            'closed_by' => $actor->id,
            'closed_at' => now(),
        ])->save();

        $this->auditRecorder->success(
            AuditLog::MODULE_SETTLEMENTS,
            'settlement.closed',
            'Settlement #'.$settlement->id.' closed',
            AuditLog::ENTITY_SETTLEMENT,
            $settlement->id,
            $actor,
            ['status' => $previousStatus],
            ['status' => Settlement::STATUS_CLOSED],
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
        $matched = $items->where('status', SettlementItem::STATUS_MATCHED)->count()
            + $items->where('status', SettlementItem::STATUS_RESOLVED)->count();
        $review = $items->filter(fn (SettlementItem $item): bool => $item->needsReview())->count();

        $settlement->forceFill([
            'expected_cost' => number_format($expected, 2, '.', ''),
            'wallet_debit_total' => number_format($wallet, 2, '.', ''),
            'supplier_invoice_total' => number_format($invoice, 2, '.', ''),
            'difference' => number_format(round($invoice - $expected, 2), 2, '.', ''),
            'orders_count' => $ordersCount,
            'matched_count' => $matched,
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

    private function findItemForInvoiceLine(Settlement $settlement, ?int $orderId, string $reference): ?SettlementItem
    {
        if ($orderId) {
            $byOrder = $settlement->items()->where('order_id', $orderId)->first();

            if ($byOrder) {
                return $byOrder;
            }
        }

        if ($reference !== '') {
            return $settlement->items()
                ->where(function ($query) use ($reference): void {
                    $query->where('booking_reference', $reference)
                        ->orWhere('external_booking_id', $reference);
                })
                ->whereNotNull('order_id')
                ->first();
        }

        return null;
    }

    private function assertMutable(Settlement $settlement): void
    {
        if (! $settlement->canMutate()) {
            throw ValidationException::withMessages([
                'settlement' => 'Closed settlements cannot be modified.',
            ]);
        }
    }
}
