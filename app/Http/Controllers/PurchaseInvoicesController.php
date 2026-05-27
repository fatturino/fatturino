<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\VatRate;
use App\Http\Controllers\Concerns\HandlesDocumentPayments;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Sequence;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseInvoicesController extends Controller
{
    use HandlesDocumentPayments;

    public function index(Request $request): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year', now()->year));
        $search = $request->query('search', '');
        $filterStatus = $request->query('status', '');
        $filterPayment = $request->query('payment', '');
        $perPage = 15;

        $query = PurchaseInvoice::query()
            ->with(['contact:id,name', 'payments:id,fiscal_document_id,amount,paid_at,reference'])
            ->whereYear('date', $fiscalYear)
            ->orderByDesc('date')
            ->orderByDesc('id');

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

        $invoices = $query->paginate($perPage)->withQueryString();

        return Inertia::render('PurchaseInvoices/Index', [
            'invoices' => $invoices,
            'fiscalYear' => $fiscalYear,
            'search' => $search,
            'filterStatus' => $filterStatus,
            'filterPayment' => $filterPayment,
            'stats' => $this->stats($fiscalYear),
            'statusOptions' => $this->statusOptions(),
            'paymentOptions' => $this->paymentOptions(),
        ]);
    }

    public function edit(PurchaseInvoice $purchaseInvoice): Response
    {
        $purchaseInvoice->load('lines');

        return Inertia::render('PurchaseInvoices/Edit', [
            'invoice' => $purchaseInvoice,
            'formData' => $this->formData(),
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        if (! $purchaseInvoice->isSdiEditable()) {
            return back()->withErrors(['invoice' => 'Questa fattura non è più modificabile.']);
        }

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'number' => 'required|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate' => 'required|string',
        ]);

        $year = Carbon::parse($validated['date'])->year;

        $purchaseInvoice->update([
            'number' => $validated['number'],
            'date' => $validated['date'],
            'due_date' => $validated['due_date'] ?? null,
            'contact_id' => $validated['contact_id'],
            'sequence_id' => $validated['sequence_id'],
            'fiscal_year' => $year,
        ]);

        $purchaseInvoice->lines()->delete();
        foreach ($validated['lines'] as $line) {
            $purchaseInvoice->lines()->create($this->buildLinePayload($line));
        }

        $purchaseInvoice->calculateTotals();

        return redirect()->route('purchase-invoices.index');
    }

    public function recordPayment(Request $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        return $this->recordDocumentPayment($request, $purchaseInvoice);
    }

    public function updatePayment(Request $request, PurchaseInvoice $purchaseInvoice, Payment $payment): JsonResponse
    {
        return $this->updateDocumentPayment($request, $purchaseInvoice, $payment);
    }

    public function deletePayment(PurchaseInvoice $purchaseInvoice, Payment $payment): JsonResponse
    {
        return $this->deleteDocumentPayment($purchaseInvoice, $payment);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'contacts' => Contact::orderBy('name')->get(['id', 'name']),
            'sequences' => Sequence::where('type', 'purchase')->get(['id', 'name']),
            'vat_rates' => VatRate::options(),
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
        $base = PurchaseInvoice::query()->whereYear('date', $fiscalYear);

        $totalCount = (clone $base)->count();
        $totalGross = (int) (clone $base)->sum('total_gross');

        $unpaidInvoices = (clone $base)
            ->where('payment_status', PaymentStatus::Unpaid)
            ->get();

        $unpaidCount = $unpaidInvoices->count();
        $unpaidAmount = (int) $unpaidInvoices->sum(fn ($i) => max(0, $i->net_due - $i->total_paid));

        $overdueCount = $unpaidInvoices->filter(fn ($i) => $i->isOverdue())->count();

        return [
            'total_count' => $totalCount,
            'total_gross' => $totalGross,
            'unpaid_count' => $unpaidCount,
            'unpaid_amount' => $unpaidAmount,
            'overdue_count' => $overdueCount,
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
}
