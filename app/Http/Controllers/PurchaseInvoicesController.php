<?php

namespace App\Http\Controllers;

use App\Actions\SavePurchaseInvoice;
use App\Http\Controllers\Concerns\HandlesDocumentPayments;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Services\PostHogTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseInvoicesController extends Controller
{
    use HandlesDocumentPayments;

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

}
