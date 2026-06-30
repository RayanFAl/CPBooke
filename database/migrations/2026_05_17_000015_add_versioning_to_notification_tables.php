<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('variables');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->unsignedInteger('template_version')->default(1)->after('template_code');
            $table->json('audit_context')->nullable()->after('variables');
        });

        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->unsignedInteger('template_version')->default(1)->after('template_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropColumn('template_version');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropColumn(['template_version', 'audit_context']);
        });

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};