<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use App\Models\Sequence;
use App\Services\DocumentEventRecorder;
use App\Services\Domain\DocumentNumberingService;
use Illuminate\Support\Facades\DB;

class ConvertProformaToInvoice
{
    /**
     * Convert a proforma invoice into a draft sales invoice.
     *
     * Returns null when the proforma is not convertible or no sales sequence exists.
     */
    public function execute(ProformaInvoice $proforma): ?SalesInvoice
    {
        return DB::transaction(function () use ($proforma) {
            $proforma = ProformaInvoice::query()
                ->lockForUpdate()
                ->findOrFail($proforma->id);

            if (! $proforma->isConvertible()) {
                return null;
            }

            // Resolve the sales invoice sequence
            $sequence = Sequence::where('type', 'sales')
                ->orderByDesc('is_system')
                ->first();

            if (! $sequence) {
                return null;
            }

            $numbering = app(DocumentNumberingService::class)->reserve($sequence, now());

            // Create the sales invoice with data from the proforma
            $invoice = SalesInvoice::create([
                'number' => $numbering['number'],
                'sequential_number' => $numbering['sequential_number'],
                'date' => now()->format('Y-m-d'),
                'due_date' => $proforma->due_date,
                'contact_id' => $proforma->contact_id,
                'sequence_id' => $sequence->id,
                'fiscal_year' => $numbering['fiscal_year'],
                'status' => InvoiceStatus::Draft,
                'proforma_id' => $proforma->id,
                'document_type' => 'TD01',
                // Copy tax options
                'withholding_tax_enabled' => $proforma->withholding_tax_enabled,
                'withholding_tax_percent' => $proforma->withholding_tax_percent,
                'fund_enabled' => $proforma->fund_enabled,
                'fund_type' => $proforma->fund_type,
                'fund_percent' => $proforma->fund_percent,
                'fund_vat_rate' => $proforma->fund_vat_rate?->value,
                'fund_has_deduction' => $proforma->fund_has_deduction,
                'stamp_duty_applied' => $proforma->stamp_duty_applied,
                'stamp_duty_charged_to_customer' => $proforma->stamp_duty_charged_to_customer,
                'stamp_duty_amount' => $proforma->stamp_duty_amount,
                'payment_method' => $proforma->payment_method?->value ?? $proforma->payment_method,
                'payment_terms' => $proforma->payment_terms?->value ?? $proforma->payment_terms,
                'bank_name' => $proforma->bank_name,
                'bank_iban' => $proforma->bank_iban,
                'vat_payability' => $proforma->vat_payability,
                'split_payment' => $proforma->split_payment,
                'notes' => $proforma->notes,
            ]);

            // Copy all lines from the proforma
            foreach ($proforma->lines as $line) {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_of_measure' => $line->unit_of_measure,
                    'unit_price' => $line->unit_price,
                    'discount_percent' => $line->discount_percent,
                    'discount_amount' => $line->discount_amount,
                    'vat_rate' => $line->vat_rate?->value,
                    'total' => $line->total,
                ]);
            }

            $invoice->calculateTotals();

            foreach ($proforma->payments as $payment) {
                $invoice->payments()->create([
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at,
                    'payment_method' => $payment->payment_method,
                    'reference' => $payment->reference,
                    'bank_name' => $payment->bank_name,
                    'notes' => $payment->notes,
                ]);
            }

            $invoice->recalculatePaymentStatus();
            app(DocumentEventRecorder::class)->created($invoice);

            // Mark proforma as converted
            $proforma->update(['status' => ProformaStatus::Converted]);

            return $invoice;
        });
    }
}
