<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_wallets', function (Blueprint $table): void {
            $table->foreignId('provider_id')->nullable()->after('id')->constrained('providers')->cascadeOnDelete();
            $table->string('environment', 20)->default('production')->after('currency');
            $table->boolean('allow_negative')->default(true)->after('low_balance_threshold');
        });

        $wallets = DB::table('provider_wallets')->get();

        foreach ($wallets as $wallet) {
            $key = Str::of((string) ($wallet->provider_key ?? 'unknown'))
                ->lower()
                ->replaceMatches('/[^a-z0-9_-]+/', '-')
                ->trim('-')
                ->toString() ?: 'unknown';

            $providerId = DB::table('providers')->where('key', $key)->value('id');

            if (! $providerId) {
                $providerId = DB::table('providers')->insertGetId([
                    'name' => $wallet->provider_name ?: Str::title(str_replace(['-', '_'], ' ', $key)),
                    'key' => $key,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('provider_wallets')->where('id', $wallet->id)->update([
                'provider_id' => $providerId,
                'environment' => 'production',
                'allow_negative' => true,
            ]);
        }

        Schema::table('provider_wallets', function (Blueprint $table): void {
            $table->dropUnique(['provider_key', 'currency']);
            $table->dropIndex(['is_active', 'provider_key']);
        });

        Schema::table('provider_wallets', function (Blueprint $table): void {
            $table->dropColumn(['provider_key', 'provider_name']);
            $table->unique(['provider_id', 'currency', 'environment']);
            $table->index(['is_active', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('provider_wallets', function (Blueprint $table): void {
            $table->dropUnique(['provider_id', 'currency', 'environment']);
            $table->string('provider_key', 80)->nullable();
            $table->string('provider_name', 120)->nullable();
        });

        $wallets = DB::table('provider_wallets')->get();

        foreach ($wallets as $wallet) {
            $provider = DB::table('providers')->where('id', $wallet->provider_id)->first();

            DB::table('provider_wallets')->where('id', $wallet->id)->update([
                'provider_key' => $provider->key ?? 'unknown',
                'provider_name' => $provider->name ?? 'Unknown',
            ]);
        }

        Schema::table('provider_wallets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provider_id');
            $table->dropIndex(['is_active', 'provider_id']);
            $table->dropColumn(['environment', 'allow_negative']);
            $table->unique(['provider_key', 'currency']);
            $table->index(['is_active', 'provider_key']);
        });
    }
};