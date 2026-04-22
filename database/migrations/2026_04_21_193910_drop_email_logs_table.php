<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replaced by the generic audits table (owen-it/laravel-auditing).
        // Dev stage: no backfill of historical rows.
        Schema::dropIfExists('email_logs');
    }

    public function down(): void
    {
        // Intentionally empty: original creation migration still present in history.
    }
};
