<?php

namespace App\Actions;

use App\Enums\ProformaStatus;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class LinkProformaToInvoice
{
    /**
     * Link a convertible proforma to an existing sales invoice of the same customer.
     */
    public function execute(ProformaInvoice $proforma, SalesInvoice $invoice): ?SalesInvoice
    {
        return DB::transaction(function () use ($proforma, $invoice) {
            $proforma = ProformaInvoice::query()->lockForUpdate()->findOrFail($proforma->id);
            $invoice = SalesInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (
                ! $proforma->isConvertible()
                || $invoice->contact_id !== $proforma->contact_id
                || $invoice->proforma_id !== null
            ) {
                return null;
            }

            $invoice->update(['proforma_id' => $proforma->id]);
            $proforma->update(['status' => ProformaStatus::Converted]);

            return $invoice;
        });
    }
}
