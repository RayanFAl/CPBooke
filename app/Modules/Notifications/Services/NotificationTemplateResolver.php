<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationTemplate;

class NotificationTemplateResolver
{
    /**
     * Resolve the persisted template or create it from defaults.
     *
     * @param  array<string, mixed>  $definition
     */
    public function resolve(array $definition): NotificationTemplate
    {
        return NotificationTemplate::query()->firstOrCreate(
            ['code' => $definition['code']],
            [
                'name' => $definition['name'],
                'subject' => $definition['subject'] ?? null,
                'body' => $definition['body'],
                'channels' => $definition['channels'],
                'variables' => $definition['variables'] ?? [],
                'version' => 1,
                'is_active' => true,
            ],
        );
    }
}