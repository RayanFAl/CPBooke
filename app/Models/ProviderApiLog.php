<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id',
    'correlation_id',
    'service',
    'endpoint_key',
    'endpoint_label',
    'endpoint_path',
    'http_method',
    'status_code',
    'success',
    'response_time_ms',
    'reference_type',
    'reference_id',
    'request_body',
    'response_body',
    'context',
    'error_message',
    'occurred_at',
])]
class ProviderApiLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'request_body' => 'array',
            'response_body' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
