<?php

namespace App\Modules\Settlements\Services;

use App\Models\Approval;
use App\Models\FinancialTransaction;
use App\Models\SettlementItem;
use App\Models\User;
use App\Modules\Approvals\Services\ApprovalService;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Finance\Support\FinancialContract;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementResolutionService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly SettlementService $settlementService,
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    /**
     * @param  array{
     *     resolution: string,
     *     reason: string,
     *     resolution_note: string,
     *     amount?: float|string|null,
     *     booking_reference?: string|null,
     *     order_id?: int|null,
     *     supplier_invoice_cost?: float|string|null,
     *     drop_invoice_line?: bool
     * }  $data
     * @return array{executed: bool, item: SettlementItem, approval: Approval|null}
     */
    public function resolve(SettlementItem $item, User $actor, array $data): array
    {
        $settlement = $item->settlement;

        if ($settlement === null || ! $settlement->canMutate()) {
            throw ValidationException::withMessages([
                'settlement' => 'Closed or approved settlements cannot be modified.',
            ]);
        }

        if ($item->hasPendingApproval()) {
            throw ValidationException::withMessages([
                'item' => 'This item already has a pending adjustment approval.',
            ]);
        }

        if (! $item->needsReview()) {
            throw ValidationException::withMessages([
                'item' => 'Only items that need review can be resolved.',
            ]);
        }

        $resolution = (string) $data['resolution'];
        $reason = (string) $data['reason'];
        $reasonConfig = FinancialContract::adjustmentReasons()[$reason] ?? null;

        if ($reasonConfig === null) {
            throw ValidationException::withMessages([
                'reason' => 'Unknown settlement resolution reason.',
            ]);
        }

        if ($reasonConfig['resolution'] !== $resolution) {
            throw ValidationException::withMessages([
                'resolution' => 'This reason cannot be used with the selected resolution path.',
            ]);
        }

        if ($resolution === FinancialContract::RESOLUTION_ACCEPT_VARIANCE) {
            return $this->acceptVariance($item, $actor, $data, $reasonConfig);
        }

        if ($resolution === FinancialContract::RESOLUTION_CORRECT_DATA) {
            $this->assertNoLedgerIntent($data, $reasonConfig);
            $item = $this->correctData($item, $actor, $data, $reason);

            return [
                'executed' => true,
                'item' => $item,
                'approval' => null,
            ];
        }

        throw ValidationException::withMessages([
            'resolution' => 'Unsupported settlement resolution.',
        ]);
    }

    /**
     * Apply an approved accept-variance adjustment. Called from the approval executor.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyApprovedVariance(SettlementItem $item, User $actor, array $payload): SettlementItem
    {
        return DB::transaction(function () use ($item, $actor, $payload): SettlementItem {
            $item->loadMissing('settlement');
            $amount = round((float) ($payload['amount'] ?? 0), 2);
            $reason = (string) ($payload['reason'] ?? '');
            $note = trim((string) ($payload['resolution_note'] ?? ''));
            $reasonConfig = FinancialContract::adjustmentReasons()[$reason] ?? null;

            if ($reasonConfig === null || ! $reasonConfig['posts_ledger']) {
                throw ValidationException::withMessages([
                    'reason' => 'Approved settlement adjustment is missing a ledger posting reason.',
                ]);
            }

            if ($amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount cannot be negative.',
                ]);
            }

            $debit = (string) $reasonConfig['debit'];
            $credit = (string) $reasonConfig['credit'];

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Accept variance requires a non-zero adjustment amount.',
                ]);
            }

            $transaction = FinancialTransaction::query()->create([
                'order_id' => $item->order_id,
                'type' => FinancialTransaction::TYPE_ADJUSTMENT,
                'status' => FinancialTransaction::STATUS_EXECUTED,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $item->settlement?->currency ?? 'LYD',
                'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
                'performed_by_id' => $actor->id,
                'source' => FinancialTransaction::SOURCE_SETTLEMENT_ADJUSTMENT,
                'source_id' => $item->id,
                'reason' => $note !== '' ? $note : $reason,
                'metadata' => [
                    'settlement_id' => $item->settlement_id,
                    'settlement_item_id' => $item->id,
                    'reason' => $reason,
                    'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                ],
                'debit_account' => $debit,
                'credit_account' => $credit,
                'reference_type' => FinancialTransaction::REFERENCE_TYPE_SETTLEMENT_ITEM,
                'reference_id' => $item->id,
            ]);

            $item->forceFill([
                'status' => SettlementItem::STATUS_RESOLVED,
                'resolution_type' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'resolution_reason' => $reason,
                'resolution_amount' => number_format($amount, 2, '.', ''),
                'resolution_note' => $note,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'pending_approval_id' => null,
                'financial_transaction_id' => $transaction->id,
            ])->save();

            $settlement = $item->settlement?->fresh();

            if ($settlement) {
                $this->settlementService->recalculateTotals($settlement);
                $this->settlementService->syncWorkflowStatus($settlement->fresh());
            }

            $this->auditRecorder->success(
                AuditLog::MODULE_SETTLEMENTS,
                'settlement.item_resolved',
                'Settlement item #'.$item->id.' accepted as variance',
                AuditLog::ENTITY_SETTLEMENT,
                $item->settlement_id,
                $actor,
                null,
                [
                    'item_id' => $item->id,
                    'resolution' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                    'reason' => $reason,
                    'amount' => number_format($amount, 2, '.', ''),
                    'financial_transaction_id' => $transaction->id,
                    'adjustment_posted' => true,
                ],
            );

            return $item->refresh();
        });
    }

    public function clearPendingApproval(SettlementItem $item): void
    {
        $item->forceFill([
            'pending_approval_id' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $reasonConfig
     * @return array{executed: bool, item: SettlementItem, approval: Approval|null}
     */
    private function acceptVariance(SettlementItem $item, User $actor, array $data, array $reasonConfig): array
    {
        if (! $reasonConfig['posts_ledger']) {
            throw ValidationException::withMessages([
                'reason' => 'This reason cannot post a financial adjustment.',
            ]);
        }

        $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : null;

        if ($amount === null || $amount == 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Accept variance requires a non-zero adjustment amount.',
            ]);
        }

        if ($amount < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount cannot be negative.',
            ]);
        }

        $variance = abs((float) ($item->difference ?? 0));

        if ($amount - $variance > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment cannot exceed the item variance of '.$variance.'.',
            ]);
        }

        $note = trim((string) $data['resolution_note']);
        $reason = (string) $data['reason'];

        $result = $this->approvalService->submit(
            type: Approval::TYPE_SETTLEMENT_ADJUSTMENT,
            entityType: Approval::ENTITY_SETTLEMENT,
            entityId: (int) $item->settlement_id,
            requester: $actor,
            payload: [
                'settlement_id' => $item->settlement_id,
                'settlement_item_id' => $item->id,
                'reason' => $reason,
                'amount' => number_format($amount, 2, '.', ''),
                'resolution_note' => $note,
            ],
            reason: $note,
            ruleContext: [
                'amount' => abs($amount),
            ],
        );

        if (! $result['executed']) {
            $item->forceFill([
                'pending_approval_id' => $result['approval']?->id,
                'resolution_type' => FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                'resolution_reason' => $reason,
                'resolution_amount' => number_format(abs($amount), 2, '.', ''),
                'resolution_note' => $note,
            ])->save();

            $this->auditRecorder->success(
                AuditLog::MODULE_SETTLEMENTS,
                'settlement.variance_approval_requested',
                'Accept variance requested for item #'.$item->id,
                AuditLog::ENTITY_SETTLEMENT,
                $item->settlement_id,
                $actor,
                null,
                [
                    'item_id' => $item->id,
                    'reason' => $reason,
                    'amount' => number_format($amount, 2, '.', ''),
                    'approval_id' => $result['approval']?->id,
                ],
            );

            return [
                'executed' => false,
                'item' => $item->refresh(),
                'approval' => $result['approval'],
            ];
        }

        return [
            'executed' => true,
            'item' => $item->refresh(),
            'approval' => $result['approval'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function correctData(SettlementItem $item, User $actor, array $data, string $reason): SettlementItem
    {
        return DB::transaction(function () use ($item, $actor, $data, $reason): SettlementItem {
            $settlement = $item->settlement()->firstOrFail();

            if (! empty($data['drop_invoice_line']) && $item->order_id === null) {
                $item->delete();
                $this->settlementService->compare($settlement->fresh(), $actor);

                $this->auditRecorder->success(
                    AuditLog::MODULE_SETTLEMENTS,
                    'settlement.item_corrected',
                    'Dropped unmatched invoice line on settlement #'.$settlement->id,
                    AuditLog::ENTITY_SETTLEMENT,
                    $settlement->id,
                    $actor,
                    null,
                    ['reason' => $reason, 'resolution' => FinancialContract::RESOLUTION_CORRECT_DATA],
                );

                return new SettlementItem;
            }

            $orderId = isset($data['order_id']) ? (int) $data['order_id'] : $item->order_id;
            $reference = isset($data['booking_reference'])
                ? trim((string) $data['booking_reference'])
                : (string) $item->booking_reference;

            if ($orderId && (int) $item->order_id !== (int) $orderId) {
                $existing = $settlement->items()
                    ->where('order_id', $orderId)
                    ->where('id', '!=', $item->id)
                    ->first();

                if ($existing) {
                    if ($item->supplier_invoice_cost !== null && $existing->supplier_invoice_cost === null) {
                        $existing->forceFill([
                            'supplier_invoice_cost' => $item->supplier_invoice_cost,
                            'booking_reference' => $existing->booking_reference ?: ($reference !== '' ? $reference : $existing->booking_reference),
                        ])->save();
                    }

                    $item->delete();
                    $this->settlementService->compare($settlement->fresh(), $actor);

                    $this->auditRecorder->success(
                        AuditLog::MODULE_SETTLEMENTS,
                        'settlement.item_corrected',
                        'Merged invoice line onto order #'.$orderId,
                        AuditLog::ENTITY_SETTLEMENT,
                        $settlement->id,
                        $actor,
                        null,
                        ['reason' => $reason, 'resolution' => FinancialContract::RESOLUTION_CORRECT_DATA],
                    );

                    return $existing->refresh();
                }
            }

            $updates = [
                'resolution_type' => FinancialContract::RESOLUTION_CORRECT_DATA,
                'resolution_reason' => $reason,
                'resolution_note' => trim((string) $data['resolution_note']),
                'resolved_by' => null,
                'resolved_at' => null,
                'financial_transaction_id' => null,
            ];

            if (array_key_exists('booking_reference', $data) && $reference !== '') {
                $updates['booking_reference'] = $reference;
            }

            if (array_key_exists('order_id', $data) && $orderId) {
                $updates['order_id'] = $orderId;
            }

            if (array_key_exists('supplier_invoice_cost', $data) && $data['supplier_invoice_cost'] !== null && $data['supplier_invoice_cost'] !== '') {
                $updates['supplier_invoice_cost'] = number_format((float) $data['supplier_invoice_cost'], 2, '.', '');
            }

            $item->forceFill($updates)->save();

            $this->settlementService->compare($settlement->fresh(), $actor);

            $this->auditRecorder->success(
                AuditLog::MODULE_SETTLEMENTS,
                'settlement.item_corrected',
                'Corrected matching data for settlement item #'.$item->id,
                AuditLog::ENTITY_SETTLEMENT,
                $settlement->id,
                $actor,
                null,
                ['reason' => $reason, 'resolution' => FinancialContract::RESOLUTION_CORRECT_DATA, 'item_id' => $item->id],
            );

            return $item->fresh() ?? $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $reasonConfig
     */
    private function assertNoLedgerIntent(array $data, array $reasonConfig): void
    {
        if ($reasonConfig['posts_ledger']) {
            throw ValidationException::withMessages([
                'reason' => 'Correct data cannot use a posting reason.',
            ]);
        }

        if (isset($data['amount']) && is_numeric($data['amount']) && (float) $data['amount'] != 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Correct data cannot post a financial amount.',
            ]);
        }
    }
}
