<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'type',
    'entity_type',
    'entity_id',
    'status',
    'requested_by',
    'approved_by',
    'rejected_by',
    'reason',
    'rejection_reason',
    'payload',
    'execution_result',
    'execution_error',
    'approved_at',
    'rejected_at',
    'executed_at',
])]
class Approval extends Model
{
    public const TYPE_REFUND = 'refund';

    public const TYPE_CANCEL = 'cancel';

    public const TYPE_WALLET_DEPOSIT = 'wallet_deposit';

    public const TYPE_WALLET_ADJUSTMENT = 'wallet_adjustment';

    public const TYPE_SETTLEMENT_ADJUSTMENT = 'settlement_adjustment';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_FAILED = 'failed';

    public const ENTITY_ORDER = 'order';

    public const ENTITY_WALLET = 'wallet';

    public const ENTITY_SETTLEMENT = 'settlement';

    public const ENTITY_SUPPORT_TICKET = 'support_ticket';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'execution_result' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
