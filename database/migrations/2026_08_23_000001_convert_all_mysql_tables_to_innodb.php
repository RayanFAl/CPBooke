<?php

use App\Support\EnsuresInnoDbStorageEngine;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EnsuresInnoDbStorageEngine::applyAll();
    }

    public function down(): void
    {
        // Irreversible on purpose: InnoDB is required for foreign keys.
    }
};
