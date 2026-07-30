<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_wallet_transactions', function (Blueprint $table): void {
            $table->string('reference_type', 80)->nullable()->after('currency');
            $table->string('reference_id', 120)->nullable()->after('reference_type');
            $table->string('description', 500)->nullable()->after('reference_id');
        });

        $rows = DB::table('provider_wallet_transactions')->get();

        foreach ($rows as $row) {
            $referenceType = 'manual';
            $referenceId = null;
            $description = $row->note;

            if ($row->order_id) {
                $referenceType = 'order';
                $referenceId = (string) $row->order_id;
            } elseif (is_string($row->reference) && str_starts_with($row->reference, 'order_debit:')) {
                $referenceType = 'order';
                $referenceId = substr($row->reference, strlen('order_debit:'));
            } elseif (is_string($row->reference) && str_starts_with($row->reference, 'deposit:')) {
                $referenceType = 'manual';
                $referenceId = substr($row->reference, strlen('deposit:'));
            } elseif (is_string($row->reference) && str_starts_with($row->reference, 'adjustment:')) {
                $referenceType = 'manual';
                $referenceId = substr($row->reference, strlen('adjustment:'));
            } elseif (is_string($row->reference) && $row->reference !== '') {
                $referenceId = $row->reference;
            }

            DB::table('provider_wallet_transactions')->where('id', $row->id)->update([
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);
        }

        Schema::table('provider_wallet_transactions', function (Blueprint $table): void {
            $table->dropUnique(['provider_wallet_id', 'reference']);
            $table->dropColumn(['reference', 'note']);
            $table->unique(['provider_wallet_id', 'reference_type', 'reference_id'], 'provider_wallet_tx_reference_unique');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('provider_wallet_transactions', function (Blueprint $table): void {
            $table->dropUnique('provider_wallet_tx_reference_unique');
            $table->string('reference', 160)->nullable();
            $table->string('note', 500)->nullable();
        });

        $rows = DB::table('provider_wallet_transactions')->get();

        foreach ($rows as $row) {
            $reference = match ($row->reference_type) {
                'order' => 'order_debit:'.($row->reference_id ?? ''),
                default => ($row->type === 'deposit' ? 'deposit:' : 'adjustment:').($row->reference_id ?? $row->id),
            };

            DB::table('provider_wallet_transactions')->where('id', $row->id)->update([
                'reference' => $reference,
                'note' => $row->description,
            ]);
        }

        Schema::table('provider_wallet_transactions', function (Blueprint $table): void {
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->dropColumn(['reference_type', 'reference_id', 'description']);
            $table->unique(['provider_wallet_id', 'reference']);
        });
    }
};
