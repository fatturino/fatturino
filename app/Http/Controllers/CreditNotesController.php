<?php

namespace App\Http\Controllers;

use App\Actions\SaveCreditNote;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\HandlesDocumentEmail;
use App\Http\Controllers\Concerns\HandlesXmlSdiWorkflow;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Services\CreditNoteXmlService;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentMailer;
use App\Services\PostHogTelemetryService;
use App\Services\XmlWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditNotesController extends Controller
{
    use HandlesDocumentEmail;
    use HandlesXmlSdiWorkflow;

    public function store(Request $request, SaveCreditNote $saveCreditNote): RedirectResponse
    {
        $creditNote = $saveCreditNote->create($this->validatePayload($request, true));
        app(DocumentEventRecorder::class)->created($creditNote);
        app(PostHogTelemetryService::class)->capture(
            'credit_note_created',
            app(PostHogTelemetryService::class)->documentProperties($creditNote),
            $request->user()
        );

        return redirect()->route('credit-notes.index');
    }

    public function update(Request $request, CreditNote $creditNote, SaveCreditNote $saveCreditNote): RedirectResponse
    {
        $saveCreditNote->update($creditNote, $this->validatePayload($request));

        return redirect()->route('credit-notes.index');
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $creating = false): array
    {
        $rules = [
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'related_invoice_number' => 'nullable|string',
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
        }

        return $request->validate($rules);
    }

    public function downloadXml(
        CreditNote $creditNote,
        CreditNoteXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ) {
        return $this->downloadXmlDocument($creditNote, $xmlService, $xmlWorkflow);
    }

    public function validateXml(
        CreditNote $creditNote,
        CreditNoteXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ): JsonResponse|RedirectResponse {
        return $this->validateXmlDocument(
            $creditNote,
            $xmlService,
            $xmlWorkflow,
            'Questa nota di credito non è più modificabile.',
            'La nota di credito non può essere validata in questo stato.'
        );
    }

    public function sendToSdi(
        CreditNote $creditNote,
        CreditNoteXmlService $xmlService,
        XmlWorkflowService $xmlWorkflow
    ): JsonResponse|RedirectResponse {
        return $this->sendXmlDocumentToSdi(
            $creditNote,
            $xmlService,
            $xmlWorkflow,
            'Questa nota di credito non è più modificabile.',
            'La nota di credito deve essere validata prima dell\'invio.',
            'Nota di credito inviata allo SDI.'
        );
    }

    public function sendEmail(
        Request $request,
        CreditNote $creditNote,
        DocumentMailer $mailer
    ): JsonResponse|RedirectResponse {
        return $this->sendDocumentEmail(
            $request,
            $creditNote,
            $mailer,
            'Il cliente non ha un indirizzo email configurato.'
        );
    }

    public function emailPreview(
        CreditNote $creditNote,
        DocumentMailer $mailer
    ): JsonResponse {
        return $this->documentEmailPreview($creditNote, $mailer);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

}
