<?php

namespace App\Services;

use Carbon\Carbon;

class BusinessFingerprintService
{
    public function extractSupplierFiscalIdFromXml(string $xml): ?string
    {
        $parsed = $this->parseXml($xml);

        return $this->normalizeFiscalIdentifier(
            $this->extractPartyIdentifier($parsed->FatturaElettronicaHeader?->CedentePrestatore?->DatiAnagrafici ?? null)
        );
    }

    public function extractCustomerFiscalIdFromXml(string $xml): ?string
    {
        $parsed = $this->parseXml($xml);

        return $this->normalizeFiscalIdentifier(
            $this->extractPartyIdentifier($parsed->FatturaElettronicaHeader?->CessionarioCommittente?->DatiAnagrafici ?? null)
        );
    }

    public function normalizeFiscalIdentifier(?string $value): ?string
    {
        $normalized = $this->normalizeVatOrCf($value);

        return $normalized === '-' ? null : $normalized;
    }

    public function buildFromXml(string $xml): string
    {
        $parsed = $this->parseXml($xml);

        $header = $parsed->FatturaElettronicaHeader ?? null;
        $body = $parsed->FatturaElettronicaBody ?? null;
        $general = $body?->DatiGenerali?->DatiGeneraliDocumento;

        $supplierVat = $this->extractPartyIdentifier($header?->CedentePrestatore?->DatiAnagrafici ?? null);
        $customerVat = $this->extractPartyIdentifier($header?->CessionarioCommittente?->DatiAnagrafici ?? null);
        $documentType = $this->normalizeText($this->extractText($general?->TipoDocumento));
        $documentNumber = $this->normalizeDocumentNumber($this->extractText($general?->Numero));
        $documentDate = $this->normalizeDate($this->extractText($general?->Data));

        $totalGrossCents = $this->toCents(
            $this->extractText($general?->ImportoTotaleDocumento)
        );

        return $this->hash([
            $supplierVat,
            $customerVat,
            $documentType,
            $documentNumber,
            $documentDate,
            (string) $totalGrossCents,
            'EUR',
        ]);
    }

    public function buildFromPayload(array $payload): string
    {
        $body = $payload['fattura_elettronica_body'][0] ?? [];
        $header = $payload['fattura_elettronica_header'] ?? [];

        $supplierVat = $this->extractIdentifierFromPayload($header['cedente_prestatore']['dati_anagrafici'] ?? []);
        $customerVat = $this->extractIdentifierFromPayload($header['cessionario_committente']['dati_anagrafici'] ?? []);

        $documentData = $body['dati_generali']['dati_generali_documento'] ?? [];
        $documentType = $this->normalizeText($documentData['tipo_documento'] ?? null);
        $documentNumber = $this->normalizeDocumentNumber($documentData['numero'] ?? null);
        $documentDate = $this->normalizeDate($documentData['data'] ?? null);

        $total = $documentData['importo_totale_documento'] ?? null;
        if ($total === null) {
            $summaryRows = $body['dati_beni_servizi']['dati_riepilogo'] ?? [];
            $total = 0;
            foreach ($summaryRows as $row) {
                $total += (float) ($row['imponibile_importo'] ?? 0);
                $total += (float) ($row['imposta'] ?? 0);
            }
        }

        $totalGrossCents = $this->toCents((string) $total);

        return $this->hash([
            $supplierVat,
            $customerVat,
            $documentType,
            $documentNumber,
            $documentDate,
            (string) $totalGrossCents,
            'EUR',
        ]);
    }

    private function parseXml(string $xml): \SimpleXMLElement
    {
        $clean = preg_replace('/<p:/', '<', $xml);
        $clean = preg_replace('/<\/p:/', '</', $clean);

        $parsed = simplexml_load_string($clean, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($parsed === false) {
            throw new \RuntimeException('Unable to parse XML for business fingerprint');
        }

        return $parsed;
    }

    private function extractPartyIdentifier(?\SimpleXMLElement $anagrafica): string
    {
        if (! $anagrafica) {
            return '-';
        }

        $idCode = $this->extractText($anagrafica->IdFiscaleIVA->IdCodice ?? null);
        $taxCode = $this->extractText($anagrafica->CodiceFiscale ?? null);

        return $this->normalizeVatOrCf($idCode ?: $taxCode);
    }

    private function extractIdentifierFromPayload(array $data): string
    {
        $idCode = $data['id_fiscale_iva']['id_codice'] ?? null;
        $taxCode = $data['codice_fiscale'] ?? null;

        return $this->normalizeVatOrCf($idCode ?: $taxCode);
    }

    private function extractText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeVatOrCf(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        $clean = strtoupper(preg_replace('/\s+/', '', $value));

        if (preg_match('/^[A-Z]{2}[A-Z0-9]+$/', $clean) === 1) {
            $clean = substr($clean, 2);
        }

        return $clean !== '' ? $clean : '-';
    }

    private function normalizeText(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        $clean = strtoupper(trim($value));

        return $clean === '' ? '-' : $clean;
    }

    private function normalizeDocumentNumber(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        $clean = strtoupper(trim(preg_replace('/\s+/', ' ', $value)));

        return $clean === '' ? '-' : $clean;
    }

    private function normalizeDate(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return '-';
        }
    }

    private function toCents(?string $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round((float) str_replace(',', '.', $value) * 100);
    }

    private function hash(array $parts): string
    {
        $canonical = implode('|', array_map(fn ($part) => $part === '' ? '-' : $part, $parts));

        return hash('sha256', $canonical);
    }
}
