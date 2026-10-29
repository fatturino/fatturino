<?php

use App\Services\Domain\SelfInvoiceDuplicateGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->string(SelfInvoiceDuplicateGuard::KEY_COLUMN, 64)->nullable()->after('related_invoice_date');
        });

        $guard = new SelfInvoiceDuplicateGuard;
        DB::table('fiscal_documents')
            ->where('type', 'self_invoice')
            ->orderBy('id')
            ->each(function (object $document) use ($guard): void {
                $key = $guard->keyFromAttributes((array) $document);
                if ($key === null) {
                    return;
                }

                $canonicalExists = DB::table('fiscal_documents')
                    ->where(SelfInvoiceDuplicateGuard::KEY_COLUMN, $key)
                    ->exists();

                if ($canonicalExists) {
                    Log::warning('Historical duplicate self-invoice left unindexed during duplicate-key migration', [
                        'fiscal_document_id' => $document->id,
                    ]);

                    return;
                }

                DB::table('fiscal_documents')
                    ->where('id', $document->id)
                    ->update([SelfInvoiceDuplicateGuard::KEY_COLUMN => $key]);
            });

        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->unique(SelfInvoiceDuplicateGuard::KEY_COLUMN, 'fiscal_documents_self_invoice_duplicate_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->dropUnique('fiscal_documents_self_invoice_duplicate_key_unique');
            $table->dropColumn(SelfInvoiceDuplicateGuard::KEY_COLUMN);
        });
    }
};
