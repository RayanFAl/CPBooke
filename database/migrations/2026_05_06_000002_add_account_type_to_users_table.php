<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'account_type')) {
                $table->string('account_type', 20)
                    ->default('customer')
                    ->after('is_admin');
            }
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update(['account_type' => 'admin']);

        DB::table('users')
            ->whereNull('account_type')
            ->orWhere('account_type', '')
            ->update(['account_type' => 'customer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'account_type')) {
                $table->dropColumn('account_type');
            }
        });
    }
};