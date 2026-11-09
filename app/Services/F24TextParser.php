<?php

namespace App\Services;

use App\Support\F24TributeCode;

/**
 * Heuristic parser that maps extracted F24 text into draft payment fields.
 * Prefers Zucchetti / Mod. F24 layout from pdftotext -layout (section + row columns).
 */
class F24TextParser
{
    private const CREDIT_GAP_THRESHOLD = 50;

    private const AMOUNT = '\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2}';

    /**
     * @return array{
     *     paid_at: ?string,
     *     protocol_number: ?string,
     *     lines: list<array<string, mixed>>,
     *     matched: bool
     * }
     */
    public function parse(string $text): array
    {
        $normalized = $this->normalize($text);
        $lines = $this->parseLayoutSections($normalized);

        if ($lines === []) {
            $lines = $this->parseCompactFallback($normalized);
        }

        return [
            'paid_at' => $this->guessPaidAt($normalized),
            'protocol_number' => $this->guessProtocol($normalized),
            'lines' => array_values($lines),
            'matched' => $lines !== [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseLayoutSections(string $text): array
    {
        $sections = $this->sliceSections($text);
        $lines = [];

        foreach ($sections as $name => $body) {
            $lines = array_merge($lines, match ($name) {
                'erario' => $this->parseTributeRows($body, defaultSection: 'erario'),
                'regioni' => $this->parseTributeRows($body, defaultSection: 'regioni'),
                'imu' => $this->parseTributeRows($body, defaultSection: 'imu'),
                'inps' => $this->parseInpsRows($body),
                default => [],
            });
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function sliceSections(string $text): array
    {
        $pattern = '/SEZIONE\s+(ERARIO|INPS|REGIONI|IMU\s+E\s+ALTRI\s+TRIBUTI\s+LOCALI|ALTRI\s+ENTI\s+PREVIDENZIALI[^\n]*)/iu';
        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $sections = [];
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $label = mb_strtoupper(trim($matches[1][$i][0]));
            $start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($text);
            $body = substr($text, $start, $end - $start);

            $key = match (true) {
                str_starts_with($label, 'ERARIO') => 'erario',
                str_starts_with($label, 'INPS') => 'inps',
                str_starts_with($label, 'REGIONI') => 'regioni',
                str_starts_with($label, 'IMU') => 'imu',
                default => 'other_enti',
            };

            $sections[$key] = $body;
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTributeRows(string $body, string $defaultSection): array
    {
        $lines = [];
        // Installment/rateazione is optional: quarterly IVA often prints only code + year + amount.
        $pattern = '/\b(\d{4})(?:\s+(\d{4}))?\s+(20\d{2})(\s+)('.self::AMOUNT.')(?:\s+('.self::AMOUNT.'))?/u';

        if (! preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $m) {
            $code = $m[1];
            if (in_array($code, ['1200'], true) && F24TributeCode::find($code) === null) {
                continue;
            }

            $installment = $m[2] !== '' ? $m[2] : null;
            $year = $m[3];
            $gap = strlen($m[4]);
            $firstAmount = $m[5];
            $secondAmount = $m[6] ?? null;

            $debit = 0;
            $credit = 0;

            if ($secondAmount !== null) {
                $debit = $this->eurosToCents($firstAmount);
                $credit = $this->eurosToCents($secondAmount);
            } elseif ($gap >= self::CREDIT_GAP_THRESHOLD) {
                $credit = $this->eurosToCents($firstAmount);
            } else {
                $debit = $this->eurosToCents($firstAmount);
            }

            $lines[] = $this->enrichTributeLine(
                code: $code,
                defaultSection: $defaultSection,
                year: $year,
                installment: $installment,
                debitCents: $debit,
                creditCents: $credit,
            );
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseInpsRows(string $body): array
    {
        $lines = [];
        $pattern = '/\b(\d{4})\s+([A-Z]{1,4})\s+(\d{2})\s+(20\d{2})\s+(\d{2})\s+(20\d{2})\s+('.self::AMOUNT.')/iu';

        if (! preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $m) {
            $causal = mb_strtoupper($m[2]);
            $from = sprintf('%02d/%s', (int) $m[3], $m[4]);
            $to = sprintf('%02d/%s', (int) $m[5], $m[6]);
            $entry = F24TributeCode::find($causal);

            $lines[] = [
                'section' => 'inps',
                'category' => $entry['category'] ?? 'inps',
                'tribute_code' => null,
                'inps_causal' => $causal,
                'reference_year' => $m[4],
                'installment_or_month' => null,
                'inps_period_from' => $from,
                'inps_period_to' => $to,
                'debit_cents' => $this->eurosToCents($m[7]),
                'credit_cents' => 0,
                'label' => $entry['label'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * Compact / OCR-ish fallback used by older fixtures and free-form text.
     *
     * @return list<array<string, mixed>>
     */
    private function parseCompactFallback(string $text): array
    {
        $compact = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $lines = [];

        $tributePattern = '/\b(\d{4})\s+(\d{4})\s+(20\d{2})\s+('.self::AMOUNT.')(?:\s+('.self::AMOUNT.'))?/u';
        if (preg_match_all($tributePattern, $compact, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $debit = $this->eurosToCents($m[4]);
                $credit = isset($m[5]) ? $this->eurosToCents($m[5]) : 0;
                $lines[] = $this->enrichTributeLine(
                    code: $m[1],
                    defaultSection: 'erario',
                    year: $m[3],
                    installment: $m[2],
                    debitCents: $debit,
                    creditCents: $credit,
                );
            }
        }

        foreach (F24TributeCode::all() as $entry) {
            if ($entry['section'] === 'inps') {
                continue;
            }
            if (collect($lines)->contains(fn (array $l) => ($l['tribute_code'] ?? null) === $entry['code'])) {
                continue;
            }
            if (! preg_match('/\b'.preg_quote($entry['code'], '/').'\b[^\n]{0,80}?('.self::AMOUNT.')/i', $compact, $m)) {
                continue;
            }
            $lines[] = $this->enrichTributeLine(
                code: $entry['code'],
                defaultSection: $entry['section'],
                year: $this->guessYear($compact),
                installment: $this->guessInstallment($compact),
                debitCents: $this->eurosToCents($m[1]),
                creditCents: 0,
            );
        }

        $inpsLayout = $this->parseInpsRows($compact);
        if ($inpsLayout !== []) {
            return array_merge($lines, $inpsLayout);
        }

        foreach (F24TributeCode::all() as $entry) {
            if ($entry['section'] !== 'inps') {
                continue;
            }
            if (! preg_match('/\b'.preg_quote($entry['code'], '/').'\b/i', $compact)) {
                continue;
            }

            $amount = 0;
            if (preg_match('/\b'.preg_quote($entry['code'], '/').'\b[^\n]{0,120}?('.self::AMOUNT.')/i', $compact, $m)) {
                $amount = $this->eurosToCents($m[1]);
            } elseif (preg_match('/('.self::AMOUNT.')/', $compact, $m)) {
                $amount = $this->eurosToCents($m[1]);
            }

            $from = null;
            if (preg_match('/\b(0[1-9]|1[0-2])[\/.\-](20\d{2})\b/', $compact, $pm)) {
                $from = sprintf('%02d/%s', (int) $pm[1], $pm[2]);
            }

            $lines[] = [
                'section' => 'inps',
                'category' => $entry['category'],
                'tribute_code' => null,
                'inps_causal' => $entry['code'],
                'reference_year' => $this->guessYear($compact),
                'installment_or_month' => null,
                'inps_period_from' => $from,
                'inps_period_to' => null,
                'debit_cents' => $amount,
                'credit_cents' => 0,
                'label' => $entry['label'],
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichTributeLine(
        string $code,
        string $defaultSection,
        ?string $year,
        ?string $installment,
        int $debitCents,
        int $creditCents,
    ): array {
        $entry = F24TributeCode::find($code);

        return [
            'section' => $entry['section'] ?? $defaultSection,
            'category' => $entry['category'] ?? 'other',
            'tribute_code' => $code,
            'inps_causal' => null,
            'reference_year' => $year,
            'installment_or_month' => $installment,
            'inps_period_from' => null,
            'inps_period_to' => null,
            'debit_cents' => $debitCents,
            'credit_cents' => $creditCents,
            'label' => $entry['label'] ?? null,
        ];
    }

    /** Keep horizontal spacing so debit/credit column gaps survive. */
    private function normalize(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private function guessYear(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})\b/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private function guessInstallment(string $text): ?string
    {
        if (preg_match('/\b(0[1-9]|1[0-2])(0[1-9]|1[0-2])\b/', $text, $m)) {
            return $m[0];
        }
        if (preg_match('/\b00(0[1-9]|1[0-2])\b/', $text, $m)) {
            return $m[0];
        }

        return null;
    }

    private function guessPaidAt(string $text): ?string
    {
        // Prefer the bank/payment block; ignore decree footers such as 19/06/2013.
        if (preg_match('/ESTREMI\s+DEL\s+VERSAMENTO[\s\S]{0,3000}?\bgiorno\b[\s\S]{0,400}?\b(\d{1,2})\s+(\d{1,2})\s+(20\d{2})\b/iu', $text, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/ESTREMI\s+DEL\s+VERSAMENTO[\s\S]{0,3000}?\b(\d{1,2})\s+(\d{1,2})\s+(20\d{2})\b/iu', $text, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/\bDATA\b[\s\S]{0,400}?\b(\d{1,2})\s+(\d{1,2})\s+(20\d{2})\b/u', $text, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/Scadenza\s+del\s+(\d{2})[\/.\-](\d{2})[\/.\-](20\d{2})\b/iu', $text, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        if (preg_match_all('/\b(\d{2})[\/.\-](\d{2})[\/.\-](20\d{2})\b/', $text, $matches, PREG_SET_ORDER)) {
            $candidates = array_values(array_filter(
                $matches,
                fn (array $m) => (int) $m[3] >= 2020
            ));
            if ($candidates !== []) {
                $m = $candidates[array_key_last($candidates)];

                return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
            }
        }

        if (preg_match('/\b(20\d{2})[\/.\-](\d{2})[\/.\-](\d{2})\b/', $text, $m)) {
            return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
        }

        return null;
    }

    private function guessProtocol(string $text): ?string
    {
        if (preg_match('/\b([A-Z]\d{10,}\/\d+)\b/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * When the PDF leaves ESTREMI empty, the filename often still carries the due date
     * (e.g. "scad. 20.08.2026" or "16.11.2026").
     */
    public function guessDateFromFilename(string $filename): ?string
    {
        if (preg_match('/\b(?:scad\.?\s*)?(\d{1,2})[.\-\/](\d{1,2})[.\-\/](20\d{2})\b/iu', $filename, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /**
     * Ordinary Italian quarterly IVA due dates for known tribute codes (tracking assist only).
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function guessDueDateFromIvaLines(array $lines): ?string
    {
        foreach ($lines as $line) {
            $code = (string) ($line['tribute_code'] ?? '');
            $year = (int) ($line['reference_year'] ?? 0);
            if ($year < 2020) {
                continue;
            }

            $due = match ($code) {
                '6031' => sprintf('%d-05-16', $year),
                '6032' => sprintf('%d-08-20', $year),
                '6033' => sprintf('%d-11-16', $year),
                '6035' => sprintf('%d-12-27', $year),
                default => null,
            };

            if ($due !== null) {
                return $due;
            }
        }

        return null;
    }

    private function eurosToCents(string $amount): int
    {
        $normalized = str_replace('.', '', $amount);
        $normalized = str_replace(',', '.', $normalized);

        return (int) round(((float) $normalized) * 100);
    }
}
