<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'settlement_id',
    'kind',
    'disk',
    'path',
    'original_name',
    'mime',
    'size',
    'source',
    'uploaded_by',
])]
class SettlementAttachment extends Model
{
    public const KIND_CSV = 'csv';

    public const KIND_XLSX = 'xlsx';

    public const KIND_PDF = 'pdf';

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_IMPORT_PASTE = 'import_paste';

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
