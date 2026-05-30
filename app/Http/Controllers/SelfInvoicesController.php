<?php

namespace App\Http\Controllers;

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
use App\Services\SelfInvoiceXmlService;
use App\Services\XmlWorkflowService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Carbon\Carbon;
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
        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc');
        $perPage = 15;

        $query = SelfInvoice::query()
            ->with(['contact:id,name,email', 'payments:id,fiscal_document_id,amount,paid_at,reference'])
            ->whereYear('date', $fiscalYear);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$search}%"));
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

    public function create(): Response
    {
        $this->ensureSelfInvoicesAllowed();

        $defaultSequence = Sequence::where('type', 'self_invoice')
            ->orderByDesc('is_system')
            ->first();

        return Inertia::render('SelfInvoices/Create', [
            'formData' => [
                'contacts' => Contact::orderBy('name')->get(['id', 'name']),
                'sequences' => Sequence::where('type', 'self_invoice')
                    ->get(['id', 'name', 'pattern'])
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'next_number' => $s->getFormattedNumber(),
                    ])
                    ->toArray(),
                'default_sequence_id' => $defaultSequence?->id,
                'vat_rates' => VatRate::options(),
                'document_types' => $this->documentTypes(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSelfInvoicesAllowed();

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
            'number' => 'nullable|string',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|string|in:TD17,TD18,TD19,TD28,TD29',
            'related_invoice_number' => 'nullable|string',
            'related_invoice_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate' => 'required|string',
        ]);

        $sequence = Sequence::findOrFail($validated['sequence_id']);
        $year = Carbon::parse($validated['date'])->year;
        $customNumber = trim((string) ($validated['number'] ?? ''));
        $customSequentialNumber = $this->extractSequentialNumber($customNumber);

        if ($customSequentialNumber !== null) {
            $reserved = [
                'formatted_number' => $customNumber,
                'sequential_number' => $customSequentialNumber,
            ];
        } else {
            $reserved = $sequence->reserveNextNumber($year);

            if ($customNumber !== '') {
                $reserved['formatted_number'] = $customNumber;
            }
        }

        $invoice = SelfInvoice::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $validated['date'],
            'due_date' => $validated['due_date'] ?? null,
            'contact_id' => $validated['contact_id'],
            'sequence_id' => $validated['sequence_id'],
            'fiscal_year' => $year,
            'status' => InvoiceStatus::Draft,
            'document_type' => $validated['document_type'],
            'related_invoice_number' => $validated['related_invoice_number'] ?? null,
            'related_invoice_date' => $validated['related_invoice_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['lines'] as $line) {
            $invoice->lines()->create($this->buildLinePayload($line));
        }

        $invoice->calculateTotals();

        return redirect()->route('self-invoices.index');
    }

    public function edit(SelfInvoice $selfInvoice): Response
    {
        $this->ensureSelfInvoicesAllowed();

        $selfInvoice->load('lines');

        return Inertia::render('SelfInvoices/Edit', [
            'invoice' => $selfInvoice,
            'formData' => [
                'contacts' => Contact::orderBy('name')->get(['id', 'name']),
                'sequences' => Sequence::where('type', 'self_invoice')->get(['id', 'name']),
                'vat_rates' => VatRate::options(),
                'document_types' => $this->documentTypes(),
            ],
        ]);
    }

    public function update(Request $request, SelfInvoice $selfInvoice): RedirectResponse
    {
        $this->ensureSelfInvoicesAllowed();

        if (! $selfInvoice->isSdiEditable()) {
            return back()->withErrors(['invoice' => 'Questa autofattura non è più modificabile.']);
        }

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|string|in:TD17,TD18,TD19,TD28,TD29',
            'related_invoice_number' => 'nullable|string',
            'related_invoice_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate' => 'required|string',
        ]);

        $year = Carbon::parse($validated['date'])->year;
        $nextStatus = $selfInvoice->status === InvoiceStatus::XmlValidated
            ? InvoiceStatus::Draft
            : $selfInvoice->status;

        $selfInvoice->update([
            'date' => $validated['date'],
            'due_date' => $validated['due_date'] ?? null,
            'contact_id' => $validated['contact_id'],
            'sequence_id' => $validated['sequence_id'],
            'fiscal_year' => $year,
            'status' => $nextStatus,
            'document_type' => $validated['document_type'],
            'related_invoice_number' => $validated['related_invoice_number'] ?? null,
            'related_invoice_date' => $validated['related_invoice_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $selfInvoice->lines()->delete();
        foreach ($validated['lines'] as $line) {
            $selfInvoice->lines()->create($this->buildLinePayload($line));
        }

        $selfInvoice->calculateTotals();

        return redirect()->route('self-invoices.index');
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

    private function buildLinePayload(array $line): array
    {
        $qty = (float) $line['quantity'];
        $price = (float) $line['unit_price'];
        $gross = $qty * $price;

        return [
            'description' => $line['description'],
            'quantity' => $qty,
            'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
            'unit_price' => (int) round($price * 100),
            'discount_percent' => null,
            'discount_amount' => null,
            'vat_rate' => $line['vat_rate'],
            'total' => (int) round($gross * 100),
        ];
    }

    private function extractSequentialNumber(string $number): ?int
    {
        if (! preg_match('/^\s*(\d+)/', $number, $matches)) {
            return null;
        }

        $parsed = (int) $matches[1];

        return $parsed > 0 ? $parsed : null;
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
        return collect(InvoiceStatus::cases())->map(fn ($s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ])->toArray();
    }

    private function paymentOptions(): array
    {
        return collect(PaymentStatus::cases())->map(fn ($s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ])->toArray();
    }

    private function applySorting($query, string $sort, string $direction): void
    {
        $sort = in_array($sort, ['number', 'created_at', 'contact'], true) ? $sort : 'created_at';
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

        $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }
}
