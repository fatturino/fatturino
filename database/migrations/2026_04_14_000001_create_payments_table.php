<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->comment('Payment amount in cents');
            $table->date('paid_at');
            $table->string('payment_method')->nullable()->comment('MP01-MP23 code, may differ from invoice default');
            $table->string('reference', 100)->nullable()->comment('Bank CRO/TRN, check number, PagoPA ID, etc.');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
