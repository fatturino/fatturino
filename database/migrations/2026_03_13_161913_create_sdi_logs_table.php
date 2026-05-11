<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sdi_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->index()->constrained()->cascadeOnDelete();
            $table->string('event_type');       // NS, RC, MC, DT, NE, AT, EC, sent, error
            $table->string('status');           // SdiStatus enum value
            $table->text('message')->nullable();
            $table->json('raw_payload')->nullable(); // Full webhook payload for debugging
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sdi_logs');
    }
};
