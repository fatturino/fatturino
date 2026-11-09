<?php

namespace App\Services;

use App\Enums\F24Category;
use App\Enums\F24PaymentSource;
use App\Enums\F24PaymentStatus;
use App\Enums\F24Section;
use App\Models\F24Payment;
use App\Models\F24PaymentLine;
use App\Support\F24TributeCode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class F24PaymentWriter
{
    /**
     * @param  array{
     *     public_id?: ?string,
     *     source?: string,
     *     status: string,
     *     due_date?: ?string,
     *     paid_at?: ?string,
     *     protocol_number?: ?string,
     *     notes?: ?string,
     *     source_file_path?: ?string,
     *     source_file_name?: ?string,
     *     source_file_mime?: ?string,
     *     ocr_raw?: ?array,
     *     lines: list<array<string, mixed>>
     * }  $payload
     */
    public function save(?F24Payment $payment, array $payload): F24Payment
    {
        $status = F24PaymentStatus::from($payload['status']);
        if ($status === F24PaymentStatus::Draft) {
            throw new InvalidArgumentException('Draft status cannot be persisted as a confirmed F24 payment.');
        }

        return DB::transaction(function () use ($payment, $payload, $status) {
            $payment ??= new F24Payment;

            if (! $payment->exists && ! empty($payload['public_id'])) {
                $payment->public_id = strtolower((string) $payload['public_id']);
            }

            $payment->fill([
                'source' => F24PaymentSource::from($payload['source'] ?? ($payment->exists ? $payment->source->value : F24PaymentSource::Manual->value)),
                'status' => $status,
                'due_date' => $payload['due_date'] ?? null,
                'paid_at' => $status === F24PaymentStatus::Paid
                    ? ($payload['paid_at'] ?? now()->toDateString())
                    : null,
                'protocol_number' => $payload['protocol_number'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'source_file_path' => array_key_exists('source_file_path', $payload)
                    ? $payload['source_file_path']
                    : $payment->source_file_path,
                'source_file_name' => array_key_exists('source_file_name', $payload)
                    ? $payload['source_file_name']
                    : $payment->source_file_name,
                'source_file_mime' => array_key_exists('source_file_mime', $payload)
                    ? $payload['source_file_mime']
                    : $payment->source_file_mime,
                'ocr_raw' => array_key_exists('ocr_raw', $payload)
                    ? $payload['ocr_raw']
                    : $payment->ocr_raw,
            ]);
            $payment->save();

            $payment->lines()->delete();

            foreach (array_values($payload['lines']) as $index => $line) {
                $this->createLine($payment, $line, $index);
            }

            $payment->recalculateTotals();

            return $payment->fresh(['lines']);
        });
    }

    public function markPaid(F24Payment $payment, ?string $paidAt = null, ?string $notes = null): F24Payment
    {
        $payment->forceFill([
            'status' => F24PaymentStatus::Paid,
            'paid_at' => $paidAt ?? now()->toDateString(),
            'notes' => $notes ?? $payment->notes,
        ])->save();

        return $payment->fresh(['lines']);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function createLine(F24Payment $payment, array $line, int $position): F24PaymentLine
    {
        $code = trim((string) ($line['tribute_code'] ?? $line['inps_causal'] ?? ''));
        $catalog = $code !== '' ? F24TributeCode::find($code) : null;

        $section = F24Section::tryFrom((string) ($line['section'] ?? ''))
            ?? ($catalog ? F24Section::from($catalog['section']) : F24Section::Erario);
        $category = F24Category::tryFrom((string) ($line['category'] ?? ''))
            ?? ($catalog ? F24Category::from($catalog['category']) : F24Category::Other);

        $tributeCode = $section === F24Section::Inps
            ? ($line['tribute_code'] ?? null)
            : ($line['tribute_code'] ?? ($code !== '' ? $code : null));
        $inpsCausal = $section === F24Section::Inps
            ? ($line['inps_causal'] ?? ($code !== '' ? $code : null))
            : ($line['inps_causal'] ?? null);

        return F24PaymentLine::create([
            'f24_payment_id' => $payment->id,
            'position' => $position,
            'section' => $section,
            'category' => $category,
            'tribute_code' => $tributeCode,
            'reference_year' => $line['reference_year'] ?? null,
            'installment_or_month' => $line['installment_or_month'] ?? null,
            'inps_causal' => $inpsCausal,
            'inps_period_from' => $line['inps_period_from'] ?? null,
            'inps_period_to' => $line['inps_period_to'] ?? null,
            'debit_cents' => max(0, (int) ($line['debit_cents'] ?? 0)),
            'credit_cents' => max(0, (int) ($line['credit_cents'] ?? 0)),
            'label' => $line['label'] ?? ($catalog['label'] ?? null),
        ]);
    }
}
