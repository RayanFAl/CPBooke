<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_devices', function (Blueprint $table): void {
            $table->unsignedInteger('app_version_code')->nullable()->after('app_version');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_devices', function (Blueprint $table): void {
            $table->dropColumn('app_version_code');
        });
    }
};
