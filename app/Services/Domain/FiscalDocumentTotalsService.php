<?php

namespace App\Services\Domain;

use App\Models\FiscalDocument;

class FiscalDocumentTotalsService
{
    public function recalculate(FiscalDocument $document): void
    {
        $document->calculateTotals();
        $document->recalculatePaymentStatus();
    }
}
