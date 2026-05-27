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
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('electronic_invoice'); // electronic_invoice, purchase, quote, proforma, self_invoice
            $table->string('pattern')->default('{SEQ}'); // Supports {SEQ} and {ANNO} tokens
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();

            // Prevent duplicate sequences with same name and type
            $table->unique(['name', 'type']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
