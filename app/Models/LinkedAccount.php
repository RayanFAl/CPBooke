<?php

namespace App\Models;

use Database\Factories\LinkedAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'linked_user_id',
    'linked_account_request_id',
    'relationship_type',
    'nickname',
    'can_request_payment',
    'can_receive_payment_requests',
    'auto_approve',
    'is_active',
])]
class LinkedAccount extends Model
{
    /** @use HasFactory<LinkedAccountFactory> */
    use HasFactory, HasUlids;

    public const RELATIONSHIP_PARENT = 'parent';

    public const RELATIONSHIP_SIBLING = 'sibling';

    public const RELATIONSHIP_SPOUSE = 'spouse';

    public const RELATIONSHIP_CHILD = 'child';

    public const RELATIONSHIP_FRIEND = 'friend';

    public const RELATIONSHIP_COLLEAGUE = 'colleague';

    public const RELATIONSHIP_OTHER = 'other';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_request_payment' => 'boolean',
            'can_receive_payment_requests' => 'boolean',
            'auto_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * @return array<int, string>
     */
    public static function relationshipTypes(): array
    {
        return [
            self::RELATIONSHIP_PARENT,
            self::RELATIONSHIP_SIBLING,
            self::RELATIONSHIP_SPOUSE,
            self::RELATIONSHIP_CHILD,
            self::RELATIONSHIP_FRIEND,
            self::RELATIONSHIP_COLLEAGUE,
            self::RELATIONSHIP_OTHER,
        ];
    }

    public static function inverseRelationshipType(string $type): string
    {
        return match ($type) {
            self::RELATIONSHIP_PARENT => self::RELATIONSHIP_CHILD,
            self::RELATIONSHIP_CHILD => self::RELATIONSHIP_PARENT,
            default => $type,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LinkedAccountRequest::class, 'linked_account_request_id');
    }
}
