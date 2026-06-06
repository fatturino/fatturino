<?php

namespace App\Http\Controllers;

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
use App\Services\DocumentMailer;
use App\Services\InvoiceXmlService;
use App\Services\ReportService;
use App\Services\XmlWorkflowService;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesInvoicesController extends Controller
{
    use HandlesDocumentEmail;
    use HandlesDocumentPayments;
    use HandlesXmlSdiWorkflow;

    public function index(Request $request): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year', now()->year));
        $search = $request->query('search', '');
        $filterStatus = $request->query('status', '');
        $filterPayment = $request->query('payment', '');
        $sort = $request->query('sort', 'date');
        $sort = $sort === 'created_at' ? 'date' : $sort;
        $direction = $request->query('direction', 'desc');
        $perPage = 15;

        $query = SalesInvoice::query()
            ->with(['contact:id,name,email', 'payments:id,fiscal_document_id,amount,paid_at,reference,notes,bank_name'])
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

        return Inertia::render('SalesInvoices/Index', [
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
        return Inertia::render('SalesInvoices/Create', [
            'formData' => $this->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
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
        $companySettings = app(CompanySettings::class);
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload($validated, $companySettings->company_fiscal_regime);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);

        $sequence = Sequence::findOrFail($normalized['sequence_id']);
        $year = Carbon::parse($normalized['date'])->year;
        $reserved = $sequence->reserveNextNumber($year);

        $invoice = SalesInvoice::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $normalized['sequence_id'],
            'fiscal_year' => $year,
            'status' => InvoiceStatus::Draft,
            'type' => 'sales',
            'document_type' => $normalized['document_type'],
            'notes' => $normalized['notes'] ?? null,
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => $normalized['withholding_tax_enabled'] ? ($normalized['withholding_tax_percent'] ?? null) : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_type' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_type'] ?? null) : null,
            'fund_percent' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_vat_rate'] ?? null) : null,
            'fund_has_deduction' => ($normalized['fund_enabled'] ?? false) && ($normalized['fund_has_deduction'] ?? false),
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? 200 : 0,
            'payment_method' => $normalized['payment_method'] ?? null,
            'payment_terms' => $normalized['payment_terms'] ?? null,
            'bank_name' => $normalized['bank_name'] ?? null,
            'bank_iban' => $normalized['bank_iban'] ?? null,
            'vat_payability' => ($normalized['split_payment'] ?? false) ? 'S' : $normalized['vat_payability'],
            'split_payment' => $normalized['split_payment'] ?? false,
        ]);

        // Create invoice lines
        foreach ($normalizedLines as $line) {
            $invoice->lines()->create($this->buildLinePayload($line));
        }

        $invoice->calculateTotals();

        return redirect()->route('sell-invoices.index');
    }

    public function edit(SalesInvoice $invoice): Response
    {
        $invoice->load('lines');

        return Inertia::render('SalesInvoices/Edit', [
            'invoice' => $invoice,
            'formData' => $this->formData(),
        ]);
    }

    public function update(Request $request, SalesInvoice $invoice): RedirectResponse
    {
        if (! $invoice->isSdiEditable()) {
            return back()->withErrors(['invoice' => 'Questa fattura non è più modificabile.']);
        }

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
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
        $companySettings = app(CompanySettings::class);
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload($validated, $companySettings->company_fiscal_regime);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);

        $year = Carbon::parse($normalized['date'])->year;
        $nextStatus = in_array($invoice->status, [InvoiceStatus::XmlValidated, InvoiceStatus::Sent], true)
            ? InvoiceStatus::Draft
            : $invoice->status;

        $invoice->update([
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $normalized['sequence_id'],
            'fiscal_year' => $year,
            'status' => $nextStatus,
            'document_type' => $normalized['document_type'],
            'notes' => $normalized['notes'] ?? null,
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => $normalized['withholding_tax_enabled'] ? ($normalized['withholding_tax_percent'] ?? null) : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_type' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_type'] ?? null) : null,
            'fund_percent' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_vat_rate'] ?? null) : null,
            'fund_has_deduction' => ($normalized['fund_enabled'] ?? false) && ($normalized['fund_has_deduction'] ?? false),
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? 200 : 0,
            'payment_method' => $normalized['payment_method'] ?? null,
            'payment_terms' => $normalized['payment_terms'] ?? null,
            'bank_name' => $normalized['bank_name'] ?? null,
            'bank_iban' => $normalized['bank_iban'] ?? null,
            'vat_payability' => ($normalized['split_payment'] ?? false) ? 'S' : $normalized['vat_payability'],
            'split_payment' => $normalized['split_payment'] ?? false,
        ]);

        // Replace invoice lines (delete old, create new)
        $invoice->lines()->delete();
        foreach ($normalizedLines as $line) {
            $invoice->lines()->create($this->buildLinePayload($line));
        }

        $invoice->calculateTotals();

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

    private function formData(): array
    {
        $settings = app(InvoiceSettings::class);
        $companySettings = app(CompanySettings::class);
        $isRf19 = $companySettings->company_fiscal_regime === 'RF19';

        $defaultSequence = Sequence::where('type', 'sales')
            ->orderByDesc('is_system')
            ->first();

        return [
            'contacts' => Contact::orderBy('name')->get(['id', 'name']),
            'sequences' => Sequence::where('type', 'sales')
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
            'document_types' => array_map(
                fn (array $type) => ['value' => $type['id'], 'label' => $type['name']],
                SalesDocumentType::options()
            ),
            'fund_types' => FundType::options(),
            'payment_methods' => PaymentMethod::options(),
            'payment_terms' => PaymentTerms::options(),
            'settings' => [
                'withholding_tax_enabled' => $isRf19 ? false : $settings->withholding_tax_enabled,
                'withholding_tax_percent' => $settings->withholding_tax_percent,
                'fund_enabled' => $settings->fund_enabled,
                'fund_type' => $settings->fund_type,
                'fund_percent' => $settings->fund_percent,
                'fund_vat_rate' => $settings->fund_vat_rate?->value,
                'fund_has_deduction' => $settings->fund_has_deduction,
                'auto_stamp_duty' => $settings->auto_stamp_duty,
                'stamp_duty_threshold' => $settings->stamp_duty_threshold,
                'default_payment_method' => $settings->default_payment_method,
                'default_payment_terms' => $settings->default_payment_terms,
                'default_bank_name' => $settings->default_bank_name,
                'default_bank_iban' => $settings->default_bank_iban,
                'default_vat_payability' => $isRf19 ? 'I' : ($settings->default_vat_payability ?? 'I'),
                'default_split_payment' => $isRf19 ? false : ($settings->default_split_payment ?? false),
                'default_notes' => $settings->default_notes,
            ],
            'vatPayabilityOptions' => VatPayability::options(),
            'fiscal_regime' => $companySettings->company_fiscal_regime,
        ];
    }

    /**
     * Build the persistence payload for a single invoice line (amounts in cents).
     */
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
        return app(ReportService::class)->salesInvoiceStats($fiscalYear);
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
