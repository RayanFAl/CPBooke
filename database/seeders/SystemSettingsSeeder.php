<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        if (SystemSetting::query()->exists()) {
            return;
        }

        SystemSetting::query()->create(SystemSetting::defaultAttributes());

        app(SystemSettingsService::class)->forgetCache();
    }
}
