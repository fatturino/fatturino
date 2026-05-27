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
        Schema::create('ei_inbound_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->index();
            $table->string('event_fingerprint')->unique();
            $table->string('source_uuid')->nullable()->index();
            $table->string('notification_type')->nullable()->index();
            $table->string('business_fingerprint')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->string('processing_status')->index()->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('linked_fiscal_document_id')->nullable()->index()->constrained('fiscal_documents')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ei_inbound_logs');
    }
};
