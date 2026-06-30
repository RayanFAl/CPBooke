<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'source')) {
                $table->string('source', 60)->nullable()->after('provider_name');
            }

            $table->unique('external_booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['external_booking_id']);

            if (Schema::hasColumn('orders', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
