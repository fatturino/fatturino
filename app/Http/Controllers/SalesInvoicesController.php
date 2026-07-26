<?php

namespace App\Http\Controllers;

use App\Actions\SaveSalesInvoice;
use App\Enums\FundType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\SalesDocumentType;
use App\Enums\VatPayability;
use App\Enums\VatRate;
use App\Http\Controllers\Concerns\HandlesDocumentEmail;
use App\Http\Controllers\Concerns\HandlesDocumentPayments;
use App\Http\Controllers\Concerns\HandlesXmlSdiWorkflow;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Models\Sequence;
use App\Services\CourtesyPdfService;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentMailer;
use App\Services\InvoiceXmlService;
use App\Services\PostHogTelemetryService;
use App\Services\ReportService;
use App\Services\XmlWorkflowService;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesInvoicesController extends Controller
{
    use HandlesDocumentEmail;
    use HandlesDocumentPayments;
    use HandlesXmlSdiWorkflow;

    public function store(Request $request, SaveSalesInvoice $saveSalesInvoice): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|string',
            'notes' => 'nullable|string',
            'withholding_tax_enabled' => 'boolean',
            'withholding_tax_percent' => 'nullable|string',
            'fund_enabled' => 'boolean',
            'fund_type' => 'nullable|string',
            'fund_percent' => 'nullable|string',
            'fund_vat_rate' => 'nullable|string',
            'fund_has_deduction' => 'boolean',
            'stamp_duty_applied' => 'boolean',
            'stamp_duty_charged_to_customer' => 'boolean',
            'payment_method' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_iban' => 'nullable|string',
            'vat_payability' => ['required', 'string', Rule::in(array_column(VatPayability::options(), 'id'))],
            'split_payment' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.vat_rate' => 'required|string',
        ]);
        $invoice = $saveSalesInvoice->create($validated);
        app(DocumentEventRecorder::class)->created($invoice);
        app(PostHogTelemetryService::class)->capture(
            'sales_invoice_created',
            app(PostHogTelemetryService::class)->documentProperties($invoice),
            $request->user()
        );

        return redirect()->route('sell-invoices.index');
    }

    public function update(Request $request, SalesInvoice $invoice, SaveSalesInvoice $saveSalesInvoice): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|string',
            'notes' => 'nullable|string',
            'withholding_tax_enabled' => 'boolean',
            'withholding_tax_percent' => 'nullable|string',
            'fund_enabled' => 'boolean',
            'fund_type' => 'nullable|string',
            'fund_percent' => 'nullable|string',
            'fund_vat_rate' => 'nullable|string',
            'fund_has_deduction' => 'boolean',
            'stamp_duty_applied' => 'boolean',
            'stamp_duty_charged_to_customer' => 'boolean',
            'payment_method' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_iban' => 'nullable|string',
            'vat_payability' => ['required', 'string', Rule::in(array_column(VatPayability::options(), 'id'))],
            'split_payment' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.vat_rate' => 'required|string',
        ]);
        $invoice = $saveSalesInvoice->update($invoice, $validated);
        app(PostHogTelemetryService::class)->capture(
            'sales_invoice_updated',
            app(PostHogTelemetryService::class)->documentProperties($invoice),
            $request->user()
        );

        return redirect()->route('sell-invoices.index');
    }

    public function downloadXml(
        SalesInvoice $invoice,
        InvoiceXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ) {
        return $this->downloadXmlDocument($invoice, $xmlService, $xmlWorkflow);
    }

    public function validateXml(
        SalesInvoice $invoice,
        InvoiceXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ): JsonResponse|RedirectResponse {
        return $this->validateXmlDocument(
            $invoice,
            $xmlService,
            $xmlWorkflow,
            'Questa fattura non è più modificabile.',
            'La fattura non può essere validata in questo stato.'
        );
    }

    public function sendToSdi(
        SalesInvoice $invoice,
        InvoiceXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ): JsonResponse|RedirectResponse {
        return $this->sendXmlDocumentToSdi(
            $invoice,
            $xmlService,
            $xmlWorkflow,
            'Questa fattura non è più modificabile.',
            'La fattura deve essere validata prima dell\'invio.',
            'Fattura inviata allo SDI.'
        );
    }

    public function downloadPdf(
        SalesInvoice $invoice,
        CourtesyPdfService $pdfService
    ) {
        $pdf = $pdfService->generate($invoice);
        $filename = $pdfService->generateFileName($invoice);

        return $pdf->download($filename);
    }

    public function sendEmail(
        Request $request,
        SalesInvoice $invoice,
        DocumentMailer $mailer
    ): JsonResponse|RedirectResponse {
        return $this->sendDocumentEmail(
            $request,
            $invoice,
            $mailer,
            'Il cliente non ha un indirizzo email configurato.'
        );
    }

    public function emailPreview(
        SalesInvoice $invoice,
        DocumentMailer $mailer
    ): JsonResponse {
        return $this->documentEmailPreview($invoice, $mailer);
    }

    public function recordPayment(Request $request, SalesInvoice $invoice): JsonResponse
    {
        return $this->recordDocumentPayment($request, $invoice);
    }

    public function updatePayment(Request $request, SalesInvoice $invoice, Payment $payment): JsonResponse
    {
        return $this->updateDocumentPayment($request, $invoice, $payment);
    }

    public function deletePayment(SalesInvoice $invoice, Payment $payment): JsonResponse
    {
        return $this->deleteDocumentPayment($invoice, $payment);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Build the persistence payload for a single invoice line (amounts in cents).
     */
}
