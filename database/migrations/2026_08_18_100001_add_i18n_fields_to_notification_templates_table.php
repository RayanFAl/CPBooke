<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->string('category', 40)->default('general')->after('name')->index();
            $table->string('description', 500)->nullable()->after('category');
            $table->json('translations')->nullable()->after('body');
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'preferred_locale')) {
                $table->string('preferred_locale', 8)->default('ar')->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->dropColumn(['category', 'description', 'translations']);
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'preferred_locale')) {
                $table->dropColumn('preferred_locale');
            }
        });
    }
};
