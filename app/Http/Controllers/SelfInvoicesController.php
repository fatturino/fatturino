<?php

namespace App\Http\Controllers;

use App\Actions\SaveSelfInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\VatRate;
use App\Http\Controllers\Concerns\HandlesDocumentPayments;
use App\Http\Controllers\Concerns\HandlesXmlSdiWorkflow;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\SelfInvoice;
use App\Models\Sequence;
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
use Inertia\Inertia;
use Inertia\Response;

class SelfInvoicesController extends Controller
{
    use HandlesDocumentPayments;
    use HandlesXmlSdiWorkflow;

    public function index(Request $request): Response
    {
        $this->ensureSelfInvoicesAllowed();

        $fiscalYear = (int) ($request->query('fiscal_year', now()->year));
        $search = $request->query('search', '');
        $filterStatus = $request->query('status', '');
        $filterPayment = $request->query('payment', '');
        $sort = $request->query('sort', 'date');
        $sort = $sort === 'created_at' ? 'date' : $sort;
        $direction = $request->query('direction', 'desc');
        $perPage = 15;

        $query = SelfInvoice::query()
            ->with(['contact:id,name,email', 'payments:id,fiscal_document_id,amount,paid_at,reference,notes,bank_name'])
            ->whereYear('date', $fiscalYear);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($filterStatus !== '') {
            $query->where('status', $filterStatus);
        }

        if ($filterPayment !== '') {
            $query->where('payment_status', $filterPayment);
        }

        $this->applySorting($query, $sort, $direction);

        $invoices = $query->paginate($perPage)->withQueryString();

        return Inertia::render('SelfInvoices/Index', [
            'invoices' => $invoices,
            'fiscalYear' => $fiscalYear,
            'search' => $search,
            'filterStatus' => $filterStatus,
            'filterPayment' => $filterPayment,
            'sort' => $sort,
            'direction' => $direction,
            'stats' => $this->stats($fiscalYear),
            'statusOptions' => $this->statusOptions(),
            'paymentOptions' => $this->paymentOptions(),
        ]);
    }

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

    private function documentTypes(): array
    {
        return [
            ['value' => 'TD17', 'label' => 'TD17 - Acquisto servizi dall\'estero'],
            ['value' => 'TD18', 'label' => 'TD18 - Acquisto beni intracomunitari'],
            ['value' => 'TD19', 'label' => 'TD19 - Acquisto beni ex art.17 c.2 DPR 633/72'],
            ['value' => 'TD28', 'label' => 'TD28 - Acquisti da San Marino con IVA'],
            ['value' => 'TD29', 'label' => 'TD29 - Omessa/irregolare fatturazione'],
        ];
    }

    private function stats(int $fiscalYear): array
    {
        $base = SelfInvoice::query()->whereYear('date', $fiscalYear);

        return [
            'total_count' => (clone $base)->count(),
            'total_gross' => (int) (clone $base)->sum('total_gross'),
        ];
    }

    private function statusOptions(): array
    {
        return collect(InvoiceStatus::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ])->toArray();
    }

    private function paymentOptions(): array
    {
        return collect(PaymentStatus::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ])->toArray();
    }

    private function applySorting($query, string $sort, string $direction): void
    {
        $sort = in_array($sort, ['number', 'date', 'contact'], true) ? $sort : 'date';
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'number') {
            $query->orderBy('number', $direction)->orderBy('id', $direction);

            return;
        }

        if ($sort === 'contact') {
            $query->orderBy(
                Contact::select('name')
                    ->whereColumn('contacts.id', 'fiscal_documents.contact_id')
                    ->limit(1),
                $direction
            )->orderBy('id', $direction);

            return;
        }

        $query->orderBy('date', $direction)->orderBy('id', $direction);
    }
}
