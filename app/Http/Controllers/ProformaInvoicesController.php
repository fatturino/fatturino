<?php

namespace App\Http\Controllers;

use App\Actions\ConvertProformaToInvoice;
use App\Actions\LinkProformaToInvoice;
use App\Enums\ProformaStatus;
use App\Http\Controllers\Concerns\HandlesDocumentEmail;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use App\Models\Sequence;
use App\Services\CourtesyPdfService;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentMailer;
use App\Services\Domain\DocumentNumberingService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProformaInvoicesController extends Controller
{
    use HandlesDocumentEmail;

    public function index(Request $request): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year', now()->year));
        $search = $request->query('search', '');
        $filterStatus = $request->query('status', '');
        $sort = $request->query('sort', 'date');
        $sort = $sort === 'created_at' ? 'date' : $sort;
        $direction = $request->query('direction', 'desc');
        $perPage = 15;

        $query = ProformaInvoice::query()
            ->with([
                'contact:id,name,email',
                'latestEmailEvent',
            ])
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

        $this->applySorting($query, $sort, $direction);

        $invoices = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Proforma/Index', [
            'invoices' => $invoices,
            'fiscalYear' => $fiscalYear,
            'search' => $search,
            'filterStatus' => $filterStatus,
            'sort' => $sort,
            'direction' => $direction,
            'stats' => $this->stats($fiscalYear),
            'statusOptions' => $this->proformaStatusOptions(),
            'linkableInvoices' => SalesInvoice::query()
                ->whereNull('proforma_id')
                ->orderByDesc('date')
                ->get(['id', 'number', 'contact_id', 'status', 'date', 'total_gross']),
        ]);
    }

    public function store(Request $request, DocumentNumberingService $documentNumbering): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
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
        ]);
        $companySettings = app(CompanySettings::class);
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload($validated, $companySettings->company_fiscal_regime);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);

        $sequence = Sequence::findOrFail($normalized['sequence_id']);
        $numbering = $documentNumbering->reserve($sequence, $normalized['date']);

        $invoice = ProformaInvoice::create([
            'number' => $numbering['number'],
            'sequential_number' => $numbering['sequential_number'],
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $normalized['sequence_id'],
            'fiscal_year' => $numbering['fiscal_year'],
            'status' => ProformaStatus::Draft,
            'notes' => $normalized['notes'] ?? null,
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => $normalized['withholding_tax_enabled'] ? ($normalized['withholding_tax_percent'] ?? null) : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_percent' => $normalized['fund_enabled'] ? ($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => $normalized['fund_enabled'] ? ($normalized['fund_vat_rate'] ?? null) : null,
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? 200 : 0,
            'payment_method' => $normalized['payment_method'] ?? null,
            'payment_terms' => $normalized['payment_terms'] ?? null,
            'bank_name' => $normalized['bank_name'] ?? null,
            'bank_iban' => $normalized['bank_iban'] ?? null,
        ]);

        foreach ($normalizedLines as $line) {
            $invoice->lines()->create($this->buildLinePayload($line));
        }

        $invoice->calculateTotals();
        app(DocumentEventRecorder::class)->created($invoice);

        return redirect()->route('proforma.index');
    }

    public function update(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        if (! in_array($proformaInvoice->status, [ProformaStatus::Draft, ProformaStatus::Sent])) {
            return back()->withErrors(['invoice' => 'Questa proforma non è più modificabile.']);
        }

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
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
        ]);
        $companySettings = app(CompanySettings::class);
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload($validated, $companySettings->company_fiscal_regime);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);

        $year = Carbon::parse($normalized['date'])->year;

        $proformaInvoice->update([
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $normalized['sequence_id'],
            'fiscal_year' => $year,
            'notes' => $normalized['notes'] ?? null,
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => $normalized['withholding_tax_enabled'] ? ($normalized['withholding_tax_percent'] ?? null) : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_percent' => $normalized['fund_enabled'] ? ($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => $normalized['fund_enabled'] ? ($normalized['fund_vat_rate'] ?? null) : null,
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? 200 : 0,
            'payment_method' => $normalized['payment_method'] ?? null,
            'payment_terms' => $normalized['payment_terms'] ?? null,
            'bank_name' => $normalized['bank_name'] ?? null,
            'bank_iban' => $normalized['bank_iban'] ?? null,
        ]);

        $proformaInvoice->lines()->delete();
        foreach ($normalizedLines as $line) {
            $proformaInvoice->lines()->create($this->buildLinePayload($line));
        }

        $proformaInvoice->calculateTotals();

        return redirect()->route('proforma.index');
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

    private function buildLinePayload(array $line): array
    {
        $qty = (float) $line['quantity'];
        $price = (float) $line['unit_price'];
        $gross = $qty * $price;

        $discountPercent = isset($line['discount_percent']) && $line['discount_percent'] !== null && $line['discount_percent'] !== ''
            ? (float) $line['discount_percent']
            : null;

        $discountedTotal = $discountPercent !== null && $discountPercent > 0
            ? $gross * (1 - $discountPercent / 100)
            : $gross;

        $discountAmount = ($discountPercent !== null && $discountPercent > 0)
            ? (int) round(($gross - $discountedTotal) * 100)
            : null;

        return [
            'description' => $line['description'],
            'quantity' => $qty,
            'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
            'unit_price' => (int) round($price * 100),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'vat_rate' => $line['vat_rate'],
            'total' => (int) round($discountedTotal * 100),
        ];
    }

    private function stats(int $fiscalYear): array
    {
        $base = ProformaInvoice::query()->whereYear('date', $fiscalYear);

        return [
            'total_count' => (clone $base)->count(),
            'total_gross' => (int) (clone $base)->sum('total_gross'),
            'converted_count' => (clone $base)->where('status', 'converted')->count(),
            'draft_count' => (clone $base)->where('status', 'draft')->count(),
        ];
    }

    private function proformaStatusOptions(): array
    {
        return collect(ProformaStatus::cases())->map(fn($s) => [
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
