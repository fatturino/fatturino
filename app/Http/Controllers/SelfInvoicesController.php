<?php

namespace App\Http\Controllers;

use App\Actions\SaveSelfInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\HandlesDocumentPayments;
use App\Http\Controllers\Concerns\HandlesXmlSdiWorkflow;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\SelfInvoice;
use App\Services\CourtesyPdfService;
use App\Services\DocumentEventRecorder;
use App\Services\PostHogTelemetryService;
use App\Services\SelfInvoiceXmlService;
use App\Services\XmlWorkflowService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SelfInvoicesController extends Controller
{
    use HandlesDocumentPayments;
    use HandlesXmlSdiWorkflow;

    public function store(Request $request, SaveSelfInvoice $saveSelfInvoice): RedirectResponse
    {
        $this->ensureSelfInvoicesAllowed();
        $invoice = $saveSelfInvoice->create($this->validatePayload($request, true));
        app(DocumentEventRecorder::class)->created($invoice);
        app(PostHogTelemetryService::class)->capture(
            'self_invoice_created',
            app(PostHogTelemetryService::class)->documentProperties($invoice),
            $request->user()
        );

        return redirect()->route('self-invoices.index');
    }

    public function update(Request $request, SelfInvoice $selfInvoice, SaveSelfInvoice $saveSelfInvoice): RedirectResponse
    {
        $this->ensureSelfInvoicesAllowed();

        $saveSelfInvoice->update($selfInvoice, $this->validatePayload($request));

        return redirect()->route('self-invoices.index');
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $creating = false): array
    {
        $rules = [
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|string|in:TD17,TD18,TD19,TD28,TD29',
            'related_invoice_number' => 'nullable|string|max:20',
            'related_invoice_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate' => 'required|string',
        ];

        if ($creating) {
            $rules['sequence_id'] = 'required|exists:sequences,id';
            $rules['number'] = 'nullable|string';
        }

        return $request->validate($rules);
    }

    public function downloadXml(
        SelfInvoice $selfInvoice,
        SelfInvoiceXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ) {
        $this->ensureSelfInvoicesAllowed();

        return $this->downloadXmlDocument($selfInvoice, $xmlService, $xmlWorkflow);
    }

    public function validateXml(
        SelfInvoice $selfInvoice,
        SelfInvoiceXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ): JsonResponse|RedirectResponse {
        $this->ensureSelfInvoicesAllowed();

        return $this->validateXmlDocument(
            $selfInvoice,
            $xmlService,
            $xmlWorkflow,
            'Questa autofattura non è più modificabile.',
            'L\'autofattura non può essere validata in questo stato.'
        );
    }

    public function sendToSdi(
        SelfInvoice $selfInvoice,
        SelfInvoiceXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ): JsonResponse|RedirectResponse {
        $this->ensureSelfInvoicesAllowed();

        return $this->sendXmlDocumentToSdi(
            $selfInvoice,
            $xmlService,
            $xmlWorkflow,
            'Questa autofattura non è più modificabile.',
            'L\'autofattura deve essere validata prima dell\'invio.',
            'Autofattura inviata allo SDI.'
        );
    }

    public function downloadPdf(
        SelfInvoice $selfInvoice,
        CourtesyPdfService $pdfService
    ) {
        $this->ensureSelfInvoicesAllowed();

        $pdf = $pdfService->generate($selfInvoice);
        $filename = $pdfService->generateFileName($selfInvoice);

        return $pdf->download($filename);
    }

    public function recordPayment(Request $request, SelfInvoice $selfInvoice): JsonResponse
    {
        $this->ensureSelfInvoicesAllowed();

        return $this->recordDocumentPayment($request, $selfInvoice);
    }

    public function updatePayment(Request $request, SelfInvoice $selfInvoice, Payment $payment): JsonResponse
    {
        $this->ensureSelfInvoicesAllowed();

        return $this->updateDocumentPayment($request, $selfInvoice, $payment);
    }

    public function deletePayment(SelfInvoice $selfInvoice, Payment $payment): JsonResponse
    {
        $this->ensureSelfInvoicesAllowed();

        return $this->deleteDocumentPayment($selfInvoice, $payment);
    }

    private function ensureSelfInvoicesAllowed(): void
    {
        $settings = app(CompanySettings::class);
        $allowed = FiscalRegimePolicy::supportsSelfInvoices(
            $settings->company_fiscal_regime,
            $settings->rf19_self_invoices_enabled
        );

        abort_if(! $allowed, 403, 'Le autofatture sono disabilitate per il regime fiscale corrente.');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

}
