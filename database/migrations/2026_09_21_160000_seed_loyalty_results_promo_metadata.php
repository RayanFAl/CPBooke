<?php

use App\Modules\Loyalty\Support\LoyaltyResultsPromo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_settings')) {
            return;
        }

        $row = DB::table('loyalty_settings')->orderBy('id')->first();

        if ($row === null) {
            return;
        }

        $metadata = json_decode((string) ($row->metadata ?: '{}'), true);
        $metadata = is_array($metadata) ? $metadata : [];

        if (! isset($metadata['results_promo']) || ! is_array($metadata['results_promo'])) {
            $metadata['results_promo'] = LoyaltyResultsPromo::defaults();
        } else {
            $metadata['results_promo'] = LoyaltyResultsPromo::resolve($metadata['results_promo']);
        }

        DB::table('loyalty_settings')->where('id', $row->id)->update([
            'metadata' => json_encode($metadata),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep promo copy; non-destructive.
    }
};
