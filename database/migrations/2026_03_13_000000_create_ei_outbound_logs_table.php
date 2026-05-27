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
        Schema::create('ei_outbound_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->index()->constrained()->cascadeOnDelete();
            $table->string('source_uuid')->nullable()->index();
            $table->string('event_type');
            $table->string('status');
            $table->text('message')->nullable();
            $table->string('business_fingerprint')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_document_id', 'event_type', 'status'], 'ei_outbound_doc_event_status_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ei_outbound_logs');
    }
};
