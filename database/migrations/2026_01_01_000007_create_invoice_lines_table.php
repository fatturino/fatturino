<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit_of_measure', 10)->nullable();
            $table->unsignedBigInteger('unit_price')->comment('Unit price in cents');
            $table->decimal('discount_percent', 5, 2)->nullable()->comment('Discount percentage (e.g. 10.00 = 10%)');
            $table->unsignedBigInteger('discount_amount')->nullable()->comment('Discount amount in cents');
            $table->string('vat_rate');
            $table->unsignedBigInteger('total')->comment('Line total in cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
