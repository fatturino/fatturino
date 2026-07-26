<?php

namespace App\Http\Controllers;

use App\Actions\ConvertProformaToInvoice;
use App\Actions\LinkProformaToInvoice;
use App\Actions\SaveProformaInvoice;
use App\Enums\ProformaStatus;
use App\Http\Controllers\Concerns\HandlesDocumentEmail;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use App\Services\CourtesyPdfService;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProformaInvoicesController extends Controller
{
    use HandlesDocumentEmail;

    public function store(Request $request, SaveProformaInvoice $saveProformaInvoice): RedirectResponse
    {
        $invoice = $saveProformaInvoice->create($this->validatePayload($request, true));
        app(DocumentEventRecorder::class)->created($invoice);

        return redirect()->route('proforma.index');
    }

    public function update(Request $request, ProformaInvoice $proformaInvoice, SaveProformaInvoice $saveProformaInvoice): RedirectResponse
    {
        $saveProformaInvoice->update($proformaInvoice, $this->validatePayload($request));

        return redirect()->route('proforma.index');
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $creating = false): array
    {
        $rules = [
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'withholding_tax_enabled' => 'boolean',
            'withholding_tax_percent' => 'nullable|string',
            'fund_enabled' => 'boolean',
            'fund_percent' => 'nullable|string',
            'fund_vat_rate' => 'nullable|string',
            'stamp_duty_applied' => 'boolean',
            'payment_method' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_iban' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.vat_rate' => 'required|string',
        ];

        if ($creating) {
            $rules['sequence_id'] = 'required|exists:sequences,id';
        }

        return $request->validate($rules);
    }

    public function convert(
        Request $request,
        ProformaInvoice $proformaInvoice,
        ConvertProformaToInvoice $convertProforma,
        LinkProformaToInvoice $linkProforma
    ): RedirectResponse {
        $validated = $request->validate([
            'mode' => 'nullable|in:create,link',
            'invoice_id' => 'required_if:mode,link|nullable|integer|exists:fiscal_documents,id,type,sales',
        ]);

        $mode = $validated['mode'] ?? 'create';
        $invoice = $mode === 'link'
            ? $linkProforma->execute($proformaInvoice, SalesInvoice::findOrFail($validated['invoice_id']))
            : $convertProforma->execute($proformaInvoice);

        if (! $invoice) {
            return back()->withErrors([
                'invoice' => 'Impossibile completare l’operazione. Verifica che la proforma sia convertibile e che la fattura selezionata appartenga allo stesso cliente e non sia già collegata.',
            ]);
        }

        return redirect()->route('sell-invoices.edit', $invoice)
            ->with('toast', [
                'type' => 'success',
                'message' => $mode === 'link'
                    ? "Proforma collegata alla fattura #{$invoice->number}."
                    : "Proforma convertita in fattura #{$invoice->number}.",
            ]);
    }

    public function downloadPdf(
        ProformaInvoice $proformaInvoice,
        CourtesyPdfService $pdfService
    ) {
        $pdf = $pdfService->generateForProforma($proformaInvoice);
        $filename = $pdfService->generateProformaFileName($proformaInvoice);

        return $pdf->download($filename);
    }

    public function sendEmail(
        Request $request,
        ProformaInvoice $proformaInvoice,
        DocumentMailer $mailer
    ): JsonResponse|RedirectResponse {
        return $this->sendDocumentEmail(
            $request,
            $proformaInvoice,
            $mailer,
            'Il cliente non ha un indirizzo email configurato.'
        );
    }

    public function emailPreview(
        ProformaInvoice $proformaInvoice,
        DocumentMailer $mailer
    ): JsonResponse {
        return $this->documentEmailPreview($proformaInvoice, $mailer);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

}
