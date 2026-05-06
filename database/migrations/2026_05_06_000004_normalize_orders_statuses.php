<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'processing')
            ->update(['status' => 'pending']);

        DB::table('orders')
            ->where('status', 'refunded')
            ->update(['status' => 'cancelled']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'confirmed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'processing', 'confirmed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending'");
    }
};