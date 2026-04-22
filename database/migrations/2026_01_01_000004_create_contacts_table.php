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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Contact type flags (can be both customer and supplier)
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);

            // Basic information
            $table->string('name');
            $table->string('vat_number')->nullable();
            $table->string('tax_code')->nullable();

            // Address details
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('IT');
            $table->string('country_code', 2)->default('IT');

            // Electronic invoicing
            $table->string('sdi_code')->nullable();
            $table->string('pec')->nullable();

            // Contact information
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();

            // Additional info
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
