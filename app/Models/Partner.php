<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'contact_email',
        'notes',
        'created_by_user_id',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(PartnerApiKey::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(PartnerWebhookEndpoint::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(PartnerWebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
