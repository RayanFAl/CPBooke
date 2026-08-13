<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_notification_preferences')) {
            return;
        }

        // Product default: SMS OFF for passenger preferences.
        DB::table('user_notification_preferences')->update(['sms_enabled' => false]);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_notification_preferences MODIFY sms_enabled TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_notification_preferences')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_notification_preferences MODIFY sms_enabled TINYINT(1) NOT NULL DEFAULT 1');
        }
    }
};
