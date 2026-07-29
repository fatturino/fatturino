<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdi_outbound_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('provider');
            $table->string('status')->index();
            $table->string('active_document_lock')->nullable()->unique();
            $table->string('xml_sha256', 64);
            $table->string('business_fingerprint', 64)->index();
            $table->string('provider_uuid')->nullable()->index();
            $table->string('provider_file_id')->nullable();
            $table->unsignedSmallInteger('provider_http_status')->nullable();
            $table->string('provider_error_code')->nullable();
            $table->string('support_message', 1000)->nullable();
            $table->timestamp('provider_accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('unlocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('unlock_reason')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->index(['fiscal_document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdi_outbound_submissions');
    }
};
