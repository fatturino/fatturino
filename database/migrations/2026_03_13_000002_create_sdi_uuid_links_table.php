<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdi_uuid_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->index()->constrained('fiscal_documents')->cascadeOnDelete();
            $table->string('outbound_uuid')->nullable()->unique();
            $table->string('inbound_uuid')->nullable()->unique();
            $table->string('business_fingerprint')->index();
            $table->string('link_reason')->default('manual');
            $table->timestamps();

            $table->index(['fiscal_document_id', 'business_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdi_uuid_links');
    }
};
