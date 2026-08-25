<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('travel_search_intents')) {
            return;
        }

        Schema::table('travel_search_intents', function (Blueprint $table): void {
            if (! Schema::hasColumn('travel_search_intents', 'search_count')) {
                $table->unsignedInteger('search_count')->default(1)->after('currency');
            }

            if (! Schema::hasColumn('travel_search_intents', 'results_viewed_at')) {
                $table->timestamp('results_viewed_at')->nullable()->after('last_searched_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_search_intents')) {
            return;
        }

        Schema::table('travel_search_intents', function (Blueprint $table): void {
            if (Schema::hasColumn('travel_search_intents', 'search_count')) {
                $table->dropColumn('search_count');
            }

            if (Schema::hasColumn('travel_search_intents', 'results_viewed_at')) {
                $table->dropColumn('results_viewed_at');
            }
        });
    }
};
