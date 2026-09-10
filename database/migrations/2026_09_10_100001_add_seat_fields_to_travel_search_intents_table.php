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
            if (! Schema::hasColumn('travel_search_intents', 'last_seen_seats')) {
                $table->unsignedSmallInteger('last_seen_seats')->nullable()->after('last_seen_price');
            }

            if (! Schema::hasColumn('travel_search_intents', 'flight_number')) {
                $table->string('flight_number', 32)->nullable()->after('return_date');
            }

            if (! Schema::hasColumn('travel_search_intents', 'offer_id')) {
                $table->string('offer_id', 120)->nullable()->after('flight_number');
            }

            if (! Schema::hasColumn('travel_search_intents', 'cabin')) {
                $table->string('cabin', 32)->nullable()->after('offer_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_search_intents')) {
            return;
        }

        Schema::table('travel_search_intents', function (Blueprint $table): void {
            foreach (['last_seen_seats', 'flight_number', 'offer_id', 'cabin'] as $column) {
                if (Schema::hasColumn('travel_search_intents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
