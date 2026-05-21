<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'email_enabled',
    'in_app_enabled',
    'sms_enabled',
    'push_enabled',
    'whatsapp_enabled',
    'disabled_categories',
])]
class UserNotificationPreference extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'disabled_categories' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}