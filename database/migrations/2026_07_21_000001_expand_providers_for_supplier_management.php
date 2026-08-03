<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->string('legal_name', 160)->nullable()->after('name');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('status');
            $table->string('settlement_cycle', 30)->default('monthly')->after('commission_rate');
            $table->decimal('credit_limit', 14, 2)->nullable()->after('settlement_cycle');
            $table->string('default_currency', 3)->default('LYD')->after('credit_limit');
            $table->string('contact_name', 120)->nullable()->after('default_currency');
            $table->string('contact_email', 160)->nullable()->after('contact_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->string('integration_status', 30)->default('not_configured')->after('contact_phone');
            $table->date('contract_starts_at')->nullable()->after('integration_status');
            $table->date('contract_ends_at')->nullable()->after('contract_starts_at');
            $table->text('contract_notes')->nullable()->after('contract_ends_at');
            $table->text('notes')->nullable()->after('contract_notes');
            $table->string('website', 255)->nullable()->after('notes');
            $table->json('metadata')->nullable()->after('website');

            $table->index('integration_status');
            $table->index('settlement_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->dropIndex(['integration_status']);
            $table->dropIndex(['settlement_cycle']);
            $table->dropColumn([
                'legal_name',
                'commission_rate',
                'settlement_cycle',
                'credit_limit',
                'default_currency',
                'contact_name',
                'contact_email',
                'contact_phone',
                'integration_status',
                'contract_starts_at',
                'contract_ends_at',
                'contract_notes',
                'notes',
                'website',
                'metadata',
            ]);
        });
    }
};
