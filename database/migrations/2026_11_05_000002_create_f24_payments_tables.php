<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('f24_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('source', 32)->default('manual');
            $table->string('status', 32)->default('planned');
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->unsignedBigInteger('total_debit_cents')->default(0);
            $table->unsignedBigInteger('total_credit_cents')->default(0);
            $table->bigInteger('balance_cents')->default(0);
            $table->string('protocol_number', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('source_file_path')->nullable();
            $table->string('source_file_name')->nullable();
            $table->string('source_file_mime', 128)->nullable();
            $table->json('ocr_raw')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['status', 'paid_at']);
            $table->index('paid_at');
        });

        Schema::create('f24_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('f24_payment_id')->constrained('f24_payments')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('section', 32)->default('erario');
            $table->string('category', 32)->default('other');
            $table->string('tribute_code', 16)->nullable();
            $table->string('reference_year', 4)->nullable();
            $table->string('installment_or_month', 8)->nullable();
            $table->string('inps_causal', 16)->nullable();
            $table->string('inps_period_from', 7)->nullable();
            $table->string('inps_period_to', 7)->nullable();
            $table->unsignedBigInteger('debit_cents')->default(0);
            $table->unsignedBigInteger('credit_cents')->default(0);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['f24_payment_id', 'position']);
            $table->index('category');
            $table->index('tribute_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f24_payment_lines');
        Schema::dropIfExists('f24_payments');
    }
};
