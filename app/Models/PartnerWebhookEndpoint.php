<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PartnerWebhookEndpoint extends Model
{
    protected $fillable = [
        'partner_id',
        'url',
        'signing_secret',
        'events',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PartnerWebhookDelivery::class, 'partner_webhook_endpoint_id');
    }

    public function setSigningSecretAttribute(string $value): void
    {
        $this->attributes['signing_secret'] = Crypt::encryptString($value);
    }

    public function plainSigningSecret(): string
    {
        return Crypt::decryptString($this->attributes['signing_secret']);
    }

    public function listensFor(string $event): bool
    {
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
