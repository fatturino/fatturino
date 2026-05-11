<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Invoice type (sales, purchase, proforma, etc.)
            $table->string('type')->default('sales')->index();
            $table->string('document_type')->nullable(); // TD17, TD18, TD19, TD28 (self-invoices)

            // Invoice numbering
            $table->string('number');
            $table->unsignedInteger('sequential_number')->nullable();
            $table->foreignId('sequence_id')->nullable()->constrained()->nullOnDelete();

            // Basic information
            $table->date('date');
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->foreignId('contact_id')->constrained();

            // Related invoice (for self-invoices)
            $table->string('related_invoice_number')->nullable();
            $table->date('related_invoice_date')->nullable();

            // Links a sales invoice back to the proforma it was converted from
            $table->foreignId('proforma_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Totals (calculated automatically, stored in cents)
            $table->unsignedBigInteger('total_net')->default(0)->comment('Net total in cents');
            $table->unsignedBigInteger('total_vat')->default(0)->comment('VAT total in cents');
            $table->unsignedBigInteger('total_gross')->default(0)->comment('Gross total in cents');
            $table->unsignedBigInteger('total_paid')->default(0)->comment('Sum of all payments in cents (denormalized for query performance)');

            // Status and payment tracking
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('unpaid');
            $table->date('due_date')->nullable();

            // SDI tracking (outbound — our invoices sent to SDI)
            $table->string('sdi_id')->nullable();
            $table->string('sdi_status')->nullable();
            $table->string('sdi_uuid')->index()->nullable();
            $table->text('sdi_message')->nullable();
            $table->timestamp('sdi_sent_at')->nullable();
            $table->string('xml_path')->nullable()->comment('Relative path from storage/app/private to the persisted XML file');
            $table->string('pdf_path')->nullable()->comment('Relative path from storage/app/private to the persisted PDF file');

            // SDI passive invoice data (inbound — invoices received from SDI via sync)
            $table->string('sdi_filename')->nullable();
            $table->integer('sdi_file_id')->nullable();
            $table->timestamp('sdi_received_at')->nullable();
            $table->timestamp('sdi_synced_at')->nullable();
            $table->json('sdi_payload')->nullable();
            $table->longText('sdi_raw_xml')->nullable();
            $table->boolean('sdi_processed')->default(false);

            // Invoice origin
            $table->string('source')->default('manual'); // manual | xml_import | sdi_sync

            // Additional info
            $table->text('notes')->nullable();

            // Withholding tax (Ritenuta d'acconto)
            $table->boolean('withholding_tax_enabled')->default(false);
            $table->decimal('withholding_tax_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('withholding_tax_amount')->nullable()->comment('Amount in cents');

            // Payment details
            $table->string('payment_method')->nullable(); // MP01-MP23
            $table->string('payment_terms')->nullable(); // TP01-TP03

            // Bank details
            $table->string('bank_name')->nullable();
            $table->string('bank_iban', 34)->nullable();

            // VAT details
            $table->string('vat_payability', 1)->default('I'); // I=Immediata, D=Differita, S=Scissione
            $table->boolean('split_payment')->default(false);

            // Stamp duty (Marca da bollo)
            $table->boolean('stamp_duty_applied')->default(false);
            $table->unsignedBigInteger('stamp_duty_amount')->nullable()->comment('Amount in cents');

            // Pension fund / Cassa previdenziale
            $table->boolean('fund_enabled')->default(false);
            $table->string('fund_type')->nullable();
            $table->decimal('fund_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('fund_amount')->nullable();
            $table->string('fund_vat_rate')->nullable();
            $table->boolean('fund_has_deduction')->default(false);

            $table->timestamps();

            // Unique sequential number per sequence and fiscal year
            $table->unique(
                ['sequence_id', 'sequential_number', 'fiscal_year'],
                'invoices_sequence_sequential_year_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
