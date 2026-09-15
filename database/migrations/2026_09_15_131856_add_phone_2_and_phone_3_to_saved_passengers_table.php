<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_passengers', function (Blueprint $table): void {
            if (! Schema::hasColumn('saved_passengers', 'phone_2')) {
                $table->text('phone_2')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('saved_passengers', 'phone_3')) {
                $table->text('phone_3')->nullable()->after('phone_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saved_passengers', function (Blueprint $table): void {
            if (Schema::hasColumn('saved_passengers', 'phone_3')) {
                $table->dropColumn('phone_3');
            }

            if (Schema::hasColumn('saved_passengers', 'phone_2')) {
                $table->dropColumn('phone_2');
            }
        });
    }
};
