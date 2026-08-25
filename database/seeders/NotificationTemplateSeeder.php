<?php

namespace Database\Seeders;

use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        app(NotificationTemplateSyncService::class)->syncMissing();
    }
}
