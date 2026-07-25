<?php

namespace App\Http\Controllers;

use App\Actions\SavePurchaseInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\HandlesDocumentPayments;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Services\PostHogTelemetryService;
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
        $sort = $request->query('sort', 'date');
        $sort = $sort === 'created_at' ? 'date' : $sort;
        $direction = $request->query('direction', 'desc');
        $perPage = 15;

        $query = PurchaseInvoice::query()
            ->with(['contact:id,name', 'payments:id,fiscal_document_id,amount,paid_at,reference,notes,bank_name'])
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

        return Inertia::render('PurchaseInvoices/Index', [
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

    public function update(Request $request, PurchaseInvoice $purchaseInvoice, SavePurchaseInvoice $savePurchaseInvoice): RedirectResponse
    {
        $savePurchaseInvoice->update($purchaseInvoice, $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'number' => 'required|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate' => 'required|string',
        ]));
        app(PostHogTelemetryService::class)->capture(
            'purchase_invoice_updated',
            app(PostHogTelemetryService::class)->documentProperties($purchaseInvoice),
            $request->user()
        );

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
