<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use App\Settings\CompanySettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Facades\Storage;

class CourtesyPdfService
{
    public function __construct(
        protected CompanySettings $companySettings
    ) {}

    /**
     * Generate a courtesy PDF for a sales invoice.
     * The PDF has no fiscal value; it is a human-readable copy of the SDI XML invoice.
     */
    public function generate(Invoice $invoice): DomPdfInstance
    {
        $invoice->loadMissing(['contact', 'lines']);

        $data = [
            'invoice' => $invoice,
            'company' => $this->companySettings,
            'logo' => $this->getLogoBase64(),
            'vatSummary' => $invoice->getVatSummary(),
            'documentTitle' => __('app.pdf.courtesy_title'),
            'showSdiDisclaimer' => true,
        ];

        return Pdf::loadView('pdf.courtesy-invoice', $data)->setPaper('a4');
    }

    /**
     * Generate a courtesy PDF for a proforma invoice.
     * Proformas are not sent to SDI, so no SDI disclaimer is shown.
     */
    public function generateForProforma(ProformaInvoice $invoice): DomPdfInstance
    {
        $invoice->loadMissing(['contact', 'lines']);

        $data = [
            'invoice' => $invoice,
            'company' => $this->companySettings,
            'logo' => $this->getLogoBase64(),
            'vatSummary' => $invoice->getVatSummary(),
            'documentTitle' => __('app.pdf.proforma_title'),
            'showSdiDisclaimer' => false,
        ];

        return Pdf::loadView('pdf.courtesy-invoice', $data)->setPaper('a4');
    }

    /**
     * Generate a courtesy PDF for a credit note.
     * Uses the same invoice view template with a different document title.
     */
    public function generateForCreditNote(CreditNote $creditNote): DomPdfInstance
    {
        $creditNote->loadMissing(['contact', 'lines']);

        $data = [
            'invoice'          => $creditNote,
            'company'          => $this->companySettings,
            'logo'             => $this->getLogoBase64(),
            'vatSummary'       => $creditNote->getVatSummary(),
            'documentTitle'    => __('app.pdf.credit_note_title'),
            'showSdiDisclaimer' => true,
        ];

        return Pdf::loadView('pdf.courtesy-invoice', $data)->setPaper('a4');
    }

    /**
     * Build the PDF filename for a sales invoice download.
     */
    public function generateFileName(Invoice $invoice): string
    {
        return 'fattura-cortesia-'.$invoice->number.'.pdf';
    }

    /**
     * Build the PDF filename for a proforma invoice download.
     */
    public function generateProformaFileName(ProformaInvoice $invoice): string
    {
        return 'proforma-'.$invoice->number.'.pdf';
    }

    /**
     * Read the company logo from the public disk and return a base64-encoded data URI.
     * dompdf cannot resolve storage URLs in all environments, so we inline the image.
     */
    private function getLogoBase64(): ?string
    {
        $path = $this->companySettings->company_logo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        $mimeType = Storage::disk('public')->mimeType($path);

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
