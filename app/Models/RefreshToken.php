<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'access_token_id',
        'token_hash',
        'device_name',
        'remember_me',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'remember_me' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
