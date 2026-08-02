<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApiKey extends Model
{
    protected $fillable = [
        'partner_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'revoked_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
