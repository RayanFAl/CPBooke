<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_id',
    'module',
    'action',
    'entity_type',
    'entity_id',
    'subject',
    'status',
    'old_values',
    'new_values',
    'context',
    'ip_address',
    'user_agent',
    'created_at',
])]
class AuditLog extends Model
{
    public $timestamps = false;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const MODULE_ORDERS = 'orders';

    public const MODULE_SUPPORT = 'support';

    public const MODULE_WALLETS = 'wallets';

    public const MODULE_SETTLEMENTS = 'settlements';

    public const MODULE_APPROVALS = 'approvals';

    public const MODULE_SYSTEM = 'system';

    public const ENTITY_ORDER = 'order';

    public const ENTITY_SUPPORT_TICKET = 'support_ticket';

    public const ENTITY_PROVIDER_WALLET = 'provider_wallet';

    public const ENTITY_CUSTOMER_WALLET = 'customer_wallet';

    public const ENTITY_SETTLEMENT = 'settlement';

    public const ENTITY_APPROVAL = 'approval';

    public const ENTITY_PROVIDER = 'provider';

    public const MODULE_PROVIDERS = 'providers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
