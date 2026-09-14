<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_alerts', function (Blueprint $table): void {
            $table->timestamp('last_reminded_at')->nullable()->after('last_triggered_at');
            $table->index(['is_active', 'last_reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('seat_alerts', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'last_reminded_at']);
            $table->dropColumn('last_reminded_at');
        });
    }
};
