<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationTemplate;
use App\Modules\Notifications\Support\NotificationTemplateCatalog;

class NotificationTemplateSyncService
{
    /**
     * Ensure all catalog templates exist (does not overwrite edited content).
     *
     * @return array{created: int, existing: int}
     */
    public function syncMissing(): array
    {
        $created = 0;
        $existing = 0;

        foreach (NotificationTemplateCatalog::definitions() as $definition) {
            $template = NotificationTemplate::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                    'channels' => $definition['channels'],
                    'variables' => $definition['variables'],
                    'version' => 1,
                    'is_active' => true,
                ],
            );

            if ($template->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
        ];
    }
}
