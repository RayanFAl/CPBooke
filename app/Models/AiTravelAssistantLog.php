<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'mode',
    'message',
    'intent',
    'product',
    'source',
    'fallback',
    'fallback_reason',
    'confidence',
    'needs_clarification',
    'missing_slots',
    'slots_summary',
    'recommendations_count',
    'offers_count',
    'model',
    'latency_ms',
    'success',
    'error_message',
    'ip_address',
    'user_agent',
])]
class AiTravelAssistantLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fallback' => 'boolean',
            'needs_clarification' => 'boolean',
            'confidence' => 'float',
            'missing_slots' => 'array',
            'slots_summary' => 'array',
            'recommendations_count' => 'integer',
            'offers_count' => 'integer',
            'latency_ms' => 'integer',
            'success' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
