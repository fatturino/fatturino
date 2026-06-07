<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Http\Controllers\Concerns\HandlesDocumentEmail;
use App\Http\Controllers\Concerns\HandlesXmlSdiWorkflow;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Services\CreditNoteXmlService;
use App\Services\DocumentMailer;
use App\Services\Domain\DocumentNumberingService;
use App\Services\XmlWorkflowService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditNotesController extends Controller
{
    use HandlesDocumentEmail;
    use HandlesXmlSdiWorkflow;

    public function index(Request $request): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year', now()->year));
        $search = $request->query('search', '');
        $filterStatus = $request->query('status', '');
        $sort = $request->query('sort', 'date');
        $sort = $sort === 'created_at' ? 'date' : $sort;
        $direction = $request->query('direction', 'desc');
        $perPage = 15;

        $query = CreditNote::query()
            ->with('contact:id,name,email')
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

        $this->applySorting($query, $sort, $direction);

        $creditNotes = $query->paginate($perPage)->withQueryString();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'fiscalYear' => $fiscalYear,
            'search' => $search,
            'filterStatus' => $filterStatus,
            'sort' => $sort,
            'direction' => $direction,
            'stats' => $this->stats($fiscalYear),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        $defaultSequence = Sequence::where('type', 'credit_note')
            ->orderByDesc('is_system')
            ->first();

        return Inertia::render('CreditNotes/Create', [
            'formData' => $this->formData($defaultSequence),
        ]);
    }

    public function store(Request $request, DocumentNumberingService $documentNumbering): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
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
        ]);
        $companySettings = app(CompanySettings::class);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);
        $notes = FiscalRegimePolicy::requiresForfettarioLegalNotice($companySettings->company_fiscal_regime)
            ? FiscalRegimePolicy::appendForfettarioLegalNotices($validated['notes'] ?? null)
            : ($validated['notes'] ?? null);

        $sequence = Sequence::findOrFail($validated['sequence_id']);
        $numbering = $documentNumbering->reserve($sequence, $validated['date']);

        $creditNote = CreditNote::create([
            'number' => $numbering['number'],
            'sequential_number' => $numbering['sequential_number'],
            'date' => $validated['date'],
            'contact_id' => $validated['contact_id'],
            'sequence_id' => $validated['sequence_id'],
            'fiscal_year' => $numbering['fiscal_year'],
            'status' => InvoiceStatus::Draft,
            'document_type' => 'TD04',
            'related_invoice_number' => $validated['related_invoice_number'] ?? null,
            'related_invoice_date' => $validated['related_invoice_date'] ?? null,
            'notes' => $notes,
        ]);

        foreach ($normalizedLines as $line) {
            $creditNote->lines()->create($this->buildLinePayload($line));
        }

        $creditNote->calculateTotals();

        return redirect()->route('credit-notes.index');
    }

    public function edit(CreditNote $creditNote): Response
    {
        $creditNote->load('lines');

        return Inertia::render('CreditNotes/Edit', [
            'creditNote' => $creditNote,
            'formData' => $this->formData(),
        ]);
    }

    public function update(Request $request, CreditNote $creditNote): RedirectResponse
    {
        if (! $creditNote->isSdiEditable()) {
            return back()->withErrors(['creditNote' => 'Questa nota di credito non è più modificabile.']);
        }

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
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
        ]);
        $companySettings = app(CompanySettings::class);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);
        $notes = FiscalRegimePolicy::requiresForfettarioLegalNotice($companySettings->company_fiscal_regime)
            ? FiscalRegimePolicy::appendForfettarioLegalNotices($validated['notes'] ?? null)
            : ($validated['notes'] ?? null);

        $year = Carbon::parse($validated['date'])->year;
        $nextStatus = in_array($creditNote->status, [InvoiceStatus::XmlValidated, InvoiceStatus::Sent], true)
            ? InvoiceStatus::Draft
            : $creditNote->status;

        $creditNote->update([
            'date' => $validated['date'],
            'contact_id' => $validated['contact_id'],
            'sequence_id' => $validated['sequence_id'],
            'fiscal_year' => $year,
            'status' => $nextStatus,
            'related_invoice_number' => $validated['related_invoice_number'] ?? null,
            'related_invoice_date' => $validated['related_invoice_date'] ?? null,
            'notes' => $notes,
        ]);

        $creditNote->lines()->delete();
        foreach ($normalizedLines as $line) {
            $creditNote->lines()->create($this->buildLinePayload($line));
        }

        $creditNote->calculateTotals();

        return redirect()->route('credit-notes.index');
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

    private function formData(?Sequence $defaultSequence = null): array
    {
        $companySettings = app(CompanySettings::class);
        $isRf19 = $companySettings->company_fiscal_regime === 'RF19';

        return [
            'contacts' => Contact::orderBy('name')->get(['id', 'name']),
            'sequences' => Sequence::where('type', 'credit_note')
                ->get(['id', 'name', 'pattern'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'next_number' => $s->getFormattedNumber(),
                ])
                ->toArray(),
            'default_sequence_id' => $defaultSequence?->id,
            'vat_rates' => $isRf19
                ? array_values(array_filter(VatRate::options(), fn (array $rate): bool => $rate['id'] === FiscalRegimePolicy::FORFETTARIO_VAT_RATE))
                : VatRate::options(),
            'fiscal_regime' => $companySettings->company_fiscal_regime,
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

    private function stats(int $fiscalYear): array
    {
        $base = CreditNote::query()->whereYear('date', $fiscalYear);

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
