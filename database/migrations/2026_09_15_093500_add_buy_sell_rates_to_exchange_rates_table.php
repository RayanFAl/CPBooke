<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table): void {
            $table->decimal('buy_rate_to_lyd', 18, 8)->default(0)->after('currency_code');
            $table->decimal('sell_rate_to_lyd', 18, 8)->default(0)->after('buy_rate_to_lyd');
        });

        foreach (DB::table('exchange_rates')->get(['id', 'rate_to_lyd']) as $row) {
            $rate = $row->rate_to_lyd ?? '1.00000000';

            DB::table('exchange_rates')->where('id', $row->id)->update([
                'buy_rate_to_lyd' => $rate,
                'sell_rate_to_lyd' => $rate,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table): void {
            $table->dropColumn(['buy_rate_to_lyd', 'sell_rate_to_lyd']);
        });
    }
};
