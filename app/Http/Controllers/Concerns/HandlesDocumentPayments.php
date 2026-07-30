<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FiscalDocument;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait HandlesDocumentPayments
{
    protected function recordDocumentPayment(Request $request, FiscalDocument $document): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'bank_name' => 'nullable|string|max:120',
        ]);

        $document = DB::transaction(function () use ($document, $validated) {
            $document = $this->lockDocument($document);

            $document->payments()->create([
                'amount' => (int) round(((float) $validated['amount']) * 100),
                'paid_at' => $validated['paid_at'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
            ]);

            $document->recalculatePaymentStatus();

            return $document->fresh();
        });

        return $this->paymentResponse($document);
    }

    protected function updateDocumentPayment(Request $request, FiscalDocument $document, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'bank_name' => 'nullable|string|max:120',
        ]);

        $document = DB::transaction(function () use ($document, $payment, $validated) {
            $document = $this->lockDocument($document);
            $payment = $document->payments()->find($payment->getKey());

            abort_unless($payment, 404);

            $payment->update([
                'amount' => (int) round(((float) $validated['amount']) * 100),
                'paid_at' => $validated['paid_at'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
            ]);

            $document->recalculatePaymentStatus();

            return $document->fresh();
        });

        return $this->paymentResponse($document);
    }

    protected function deleteDocumentPayment(FiscalDocument $document, Payment $payment): JsonResponse
    {
        $document = DB::transaction(function () use ($document, $payment) {
            $document = $this->lockDocument($document);
            $payment = $document->payments()->find($payment->getKey());

            abort_unless($payment, 404);

            $payment->delete();
            $document->recalculatePaymentStatus();

            return $document->fresh();
        });

        return $this->paymentResponse($document);
    }

    private function paymentResponse(FiscalDocument $document): JsonResponse
    {
        return response()->json([
            'success' => true,
            'payment_status' => $document->paymentStatusValue(),
            'total_paid' => $document->total_paid,
            'remaining_balance' => $document->remainingBalance(),
            'payments' => $document->payments()->orderByDesc('paid_at')->orderByDesc('id')->get([
                'id',
                'fiscal_document_id',
                'amount',
                'paid_at',
                'reference',
                'notes',
                'bank_name',
            ]),
        ]);
    }

    private function lockDocument(FiscalDocument $document): FiscalDocument
    {
        return FiscalDocument::query()
            ->lockForUpdate()
            ->findOrFail($document->getKey());
    }
}
