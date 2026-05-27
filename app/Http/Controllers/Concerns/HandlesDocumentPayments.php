<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FiscalDocument;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesDocumentPayments
{
    protected function recordDocumentPayment(Request $request, FiscalDocument $document): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
        ]);

        $document->payments()->create([
            'amount' => (int) round(((float) $validated['amount']) * 100),
            'paid_at' => $validated['paid_at'] ?? null,
            'reference' => $validated['reference'] ?? null,
        ]);

        $document->recalculatePaymentStatus();
        $document->refresh();

        return $this->paymentResponse($document);
    }

    protected function updateDocumentPayment(Request $request, FiscalDocument $document, Payment $payment): JsonResponse
    {
        if ((int) $payment->fiscal_document_id !== (int) $document->id) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
        ]);

        $payment->update([
            'amount' => (int) round(((float) $validated['amount']) * 100),
            'paid_at' => $validated['paid_at'] ?? null,
            'reference' => $validated['reference'] ?? null,
        ]);

        $document->recalculatePaymentStatus();
        $document->refresh();

        return $this->paymentResponse($document);
    }

    protected function deleteDocumentPayment(FiscalDocument $document, Payment $payment): JsonResponse
    {
        if ((int) $payment->fiscal_document_id !== (int) $document->id) {
            abort(404);
        }

        $payment->delete();
        $document->recalculatePaymentStatus();
        $document->refresh();

        return $this->paymentResponse($document);
    }

    private function paymentResponse(FiscalDocument $document): JsonResponse
    {
        return response()->json([
            'success' => true,
            'payment_status' => $document->paymentStatusValue(),
            'total_paid' => $document->total_paid,
            'remaining_balance' => $document->remainingBalance(),
            'payments' => $document->payments()->orderByDesc('paid_at')->orderByDesc('id')->get(['id', 'fiscal_document_id', 'amount', 'paid_at', 'reference']),
        ]);
    }
}
