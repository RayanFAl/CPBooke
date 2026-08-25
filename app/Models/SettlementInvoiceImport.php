<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'settlement_id',
    'sequence',
    'attachment_id',
    'original_name',
    'uploaded_by',
    'uploaded_at',
    'row_count',
    'matched_count',
    'extra_count',
    'error_count',
    'errors',
    'is_active',
])]
class SettlementInvoiceImport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'errors' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(SettlementAttachment::class, 'attachment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
