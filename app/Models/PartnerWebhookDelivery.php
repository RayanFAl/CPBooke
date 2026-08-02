<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'partner_id',
        'partner_webhook_endpoint_id',
        'event',
        'status',
        'attempt_count',
        'response_code',
        'response_body',
        'payload',
        'delivered_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(PartnerWebhookEndpoint::class, 'partner_webhook_endpoint_id');
    }
}
