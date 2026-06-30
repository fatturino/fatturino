<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->index()->constrained()->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->string('channel')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('cc')->nullable();
            $table->text('subject')->nullable();
            $table->text('error_message')->nullable();
            $table->string('technical_reference_type')->nullable();
            $table->unsignedBigInteger('technical_reference_id')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fiscal_document_id', 'event_type', 'occurred_at']);
            $table->index(['technical_reference_type', 'technical_reference_id'], 'document_events_technical_reference_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_events');
    }
};
