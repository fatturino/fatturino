<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

class ConvertProformaToInvoice
{
    /**
     * Convert a proforma invoice into a sales invoice.
     * Returns the new Invoice or null if conversion is not possible.
     */
    public function execute(ProformaInvoice $proforma): ?Invoice
    {
        if (! $proforma->isConvertible()) {
            return null;
        }

        return DB::transaction(function () use ($proforma) {
            // Resolve the sales invoice sequence
            $sequence = Sequence::where('type', 'electronic_invoice')
                ->orderByDesc('is_system')
                ->first();

            if (! $sequence) {
                return null;
            }

            $year = now()->year;
            $reserved = $sequence->reserveNextNumber($year);

            // Create the sales invoice with data from the proforma
            $invoice = Invoice::create([
                'number' => $reserved['formatted_number'],
                'sequential_number' => $reserved['sequential_number'],
                'date' => now()->format('Y-m-d'),
                'contact_id' => $proforma->contact_id,
                'sequence_id' => $sequence->id,
                'fiscal_year' => $year,
                'status' => InvoiceStatus::Draft,
                'proforma_id' => $proforma->id,
                // Copy tax options
                'withholding_tax_enabled' => $proforma->withholding_tax_enabled,
                'withholding_tax_percent' => $proforma->withholding_tax_percent,
                'fund_enabled' => $proforma->fund_enabled,
                'fund_type' => $proforma->fund_type,
                'fund_percent' => $proforma->fund_percent,
                'fund_vat_rate' => $proforma->fund_vat_rate?->value,
                'fund_has_deduction' => $proforma->fund_has_deduction,
                'stamp_duty_applied' => $proforma->stamp_duty_applied,
                'stamp_duty_amount' => $proforma->stamp_duty_amount,
                'notes' => $proforma->notes,
            ]);

            // Copy all lines from the proforma
            foreach ($proforma->lines as $line) {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_of_measure' => $line->unit_of_measure,
                    'unit_price' => $line->unit_price,
                    'vat_rate' => $line->vat_rate?->value,
                    'total' => $line->total,
                ]);
            }

            $invoice->calculateTotals();

            // Mark proforma as converted
            $proforma->update(['status' => ProformaStatus::Converted]);

            return $invoice;
        });
    }
}
