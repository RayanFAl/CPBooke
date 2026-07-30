<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id',
    'event_type',
    'latency_ms',
    'success',
    'reference_type',
    'reference_id',
    'message',
    'metadata',
    'created_at',
])]
class ProviderApiEvent extends Model
{
    public $timestamps = false;

    public const TYPE_SYNC_SUCCESS = 'sync_success';

    public const TYPE_SYNC_FAILURE = 'sync_failure';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
