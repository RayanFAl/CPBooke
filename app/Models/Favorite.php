<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'type',
    'item_key',
    'status',
    'snapshot',
    'search_context',
    'expires_at',
])]
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory, HasUlids;

    public const TYPE_FLIGHT = 'flight';

    public const TYPE_HOTEL = 'hotel';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const MAX_PER_TYPE = 50;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'search_context' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Use the ULID primary key for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Get the user that owns the favorite.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_FLIGHT,
            self::TYPE_HOTEL,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * Scope favorites that belong to a user.
     *
     * @param  Builder<Favorite>  $query
     * @return Builder<Favorite>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope by favorite type.
     *
     * @param  Builder<Favorite>  $query
     * @return Builder<Favorite>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by status.
     *
     * @param  Builder<Favorite>  $query
     * @return Builder<Favorite>
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Determine whether this favorite has passed its expiry time.
     */
    public function hasExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo(Carbon::now());
    }

    /**
     * Refresh status based on expires_at when needed.
     */
    public function syncExpiryStatus(): bool
    {
        if ($this->type !== self::TYPE_FLIGHT) {
            return false;
        }

        if ($this->hasExpired() && $this->status !== self::STATUS_EXPIRED) {
            $this->forceFill(['status' => self::STATUS_EXPIRED])->save();

            return true;
        }

        return false;
    }
}
