<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'token',
    'user_id',
    'search',
    'selected_offer',
    'expires_at',
])]
class SharedFlight extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'token';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'search' => 'array',
            'selected_offer' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon
            && $this->expires_at->lessThanOrEqualTo(Carbon::now());
    }

    public static function makeToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    public function shareUrl(): string
    {
        $base = rtrim((string) config('services.shared_flight.url_base', 'https://booknow.app/s'), '/');

        return $base.'/'.$this->token;
    }
}
