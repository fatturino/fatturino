<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->after('related_invoice_date')
                ->constrained('fiscal_documents')
                ->restrictOnDelete();
            $table->unique('reversal_of_id', 'fiscal_documents_reversal_of_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->dropUnique('fiscal_documents_reversal_of_id_unique');
            $table->dropConstrainedForeignId('reversal_of_id');
        });
    }
};
