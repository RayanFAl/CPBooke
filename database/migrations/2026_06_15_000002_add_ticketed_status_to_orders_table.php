<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'draft',
            'pending_payment',
            'paid',
            'processing',
            'confirmed',
            'ticketed',
            'completed',
            'cancelled',
            'failed',
            'refunded'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('orders')
            ->where('status', 'ticketed')
            ->update(['status' => 'confirmed']);

        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'draft',
            'pending_payment',
            'paid',
            'processing',
            'confirmed',
            'completed',
            'cancelled',
            'failed',
            'refunded'
        ) NOT NULL DEFAULT 'draft'");
    }
};
