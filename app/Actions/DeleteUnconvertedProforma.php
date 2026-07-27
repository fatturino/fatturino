<?php

namespace App\Actions;

use App\Enums\ProformaStatus;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\DB;

class DeleteUnconvertedProforma
{
    public function execute(ProformaInvoice $proforma): bool
    {
        return DB::transaction(function () use ($proforma) {
            $proforma = ProformaInvoice::query()
                ->lockForUpdate()
                ->findOrFail($proforma->id);

            if ($proforma->status === ProformaStatus::Converted || $proforma->convertedInvoice()->exists()) {
                return false;
            }

            return (bool) $proforma->delete();
        });
    }
}
