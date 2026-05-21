<?php

namespace App\Models;

use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'subject',
    'body',
    'channels',
    'variables',
    'version',
    'is_active',
])]
class NotificationTemplate extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'variables' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the delivery logs generated from this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'template_code', 'code');
    }

    /**
     * Get the enabled channels for this template.
     *
     * @return array<int, string>
     */
    public function enabledChannels(): array
    {
        return collect($this->channels ?? [])
            ->filter(fn (mixed $channel): bool => is_string($channel) && in_array($channel, NotificationChannels::all(), true))
            ->values()
            ->all();
    }
}