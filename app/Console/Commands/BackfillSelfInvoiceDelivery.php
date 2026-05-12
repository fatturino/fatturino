<?php

namespace App\Console\Commands;

use App\Enums\SdiStatus;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;
use Illuminate\Console\Command;

class BackfillSelfInvoiceDelivery extends Command
{
    protected $signature = 'invoices:backfill-self-delivery
                            {number : Internal self-invoice number (e.g. AUTO-00022)}
                            {file_id : SDI file_id shared between customer-invoice and supplier-invoice}';

    protected $description = 'Backfill: mark a self-invoice as delivered and delete its duplicate purchase row (if any)';

    public function handle(): int
    {
        $number = $this->argument('number');
        $fileId = $this->argument('file_id');

        // 1. Find the self-invoice
        $selfInvoice = SelfInvoice::where('number', $number)->first();

        if (! $selfInvoice) {
            $this->error("Self-invoice '{$number}' not found.");

            return self::FAILURE;
        }

        $this->info("Found self-invoice #{$selfInvoice->id} ({$selfInvoice->number})");

        // 2. Update self-invoice
        $selfInvoice->update([
            'sdi_file_id' => $fileId,
            'sdi_status' => SdiStatus::Delivered,
            'sdi_message' => 'Consegnata (backfill: ricevuta come acquisto)',
        ]);

        $this->info("Updated sdi_status to 'delivered' and sdi_file_id to '{$fileId}'.");

        // 3. Find and delete duplicate purchase invoice (if any)
        $duplicatePurchase = PurchaseInvoice::withoutGlobalScopes()
            ->where('type', 'purchase')
            ->where('sdi_file_id', $fileId)
            ->first();

        if ($duplicatePurchase) {
            $duplicatePurchase->lines()->delete();
            $duplicatePurchase->delete();

            $this->info("Deleted duplicate purchase invoice #{$duplicatePurchase->id} ('{$duplicatePurchase->number}').");
        } else {
            $this->line('No duplicate purchase invoice found.');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
