<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceXmlImportService
{
    public function __construct(
        private DocumentStorageService $documentStorage,
    ) {}

    protected array $stats = [
        'total' => 0,
        'invoices_imported' => 0,
        'contacts_created' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    protected array $errors = [];

    /**
     * Import a single XML file content into the database.
     *
     * @param  string  $xmlContent  Raw XML string
     * @param  int  $sequenceId  Sequence ID chosen by user
     * @param  string  $category  'electronic_invoice', 'purchase', or 'self_invoice' — determines which party becomes the contact
     */
    public function importXml(string $xmlContent, ?int $sequenceId, string $category = 'electronic_invoice'): void
    {
        $this->stats['total']++;

        try {
            $xml = $this->parseXml($xmlContent);
            $header = $xml->FatturaElettronicaHeader;
            $body = $xml->FatturaElettronicaBody;

            DB::transaction(function () use ($header, $body, $sequenceId, $category, $xmlContent) {
                $isSelfInvoice = $category === 'self_invoice';
                $isPurchase = $category === 'purchase';

                // Sales: contact is the buyer (CessionarioCommittente)
                // Purchase / Self-invoice: contact is the seller (CedentePrestatore)
                $contactNode = ($isPurchase || $isSelfInvoice)
                    ? $header->CedentePrestatore
                    : $header->CessionarioCommittente;

                $contact = $this->findOrCreateContact($contactNode, $isPurchase || $isSelfInvoice);

                // Extract PEC from transmission data if present
                $pec = $this->extractText($header->DatiTrasmissione->PECDestinatario);
                if ($pec && ! $contact->pec) {
                    $contact->update(['pec' => $pec]);
                }

                // Resolve model class and default status per category
                [$modelClass, $status, $sdiStatus] = match ($category) {
                    'purchase' => [PurchaseInvoice::class, InvoiceStatus::Generated, SdiStatus::Received],
                    'self_invoice' => [SelfInvoice::class, InvoiceStatus::Sent, SdiStatus::Delivered],
                    default => [Invoice::class, InvoiceStatus::Sent, SdiStatus::Delivered],
                };

                // Check for duplicate before creating
                $datiGeneraliDoc = $body->DatiGenerali->DatiGeneraliDocumento;
                $number = $this->extractText($datiGeneraliDoc->Numero);
                $invoiceDate = $this->extractText($datiGeneraliDoc->Data);
                $year = $invoiceDate ? (int) substr($invoiceDate, 0, 4) : now()->year;

                $alreadyExists = $modelClass::where('number', $number)
                    ->where('contact_id', $contact->id)
                    ->where('fiscal_year', $year)
                    ->exists();

                if ($alreadyExists) {
                    $this->stats['skipped']++;

                    return;
                }

                // When importing as purchase, check if the XML is actually a
                // self-invoice (TD17/TD18/TD19/TD28/TD29 document types).
                // If so, redirect to self_invoice import to avoid polluting
                // the purchases section with autofatture.
                if ($isPurchase) {
                    $documentType = $this->extractText($datiGeneraliDoc->TipoDocumento);
                    $selfInvoiceTypes = ['TD17', 'TD18', 'TD19', 'TD28', 'TD29'];

                    if (in_array($documentType, $selfInvoiceTypes, true)) {
                        // Already exists? Skip duplicate
                        if ($number && SelfInvoice::where('number', $number)->exists()) {
                            $this->stats['skipped']++;

                            return;
                        }

                        // Redirect: import as self_invoice instead of purchase
                        $isSelfInvoice = true;
                        $isPurchase = false;
                        $modelClass = SelfInvoice::class;
                        $status = InvoiceStatus::Sent;
                        $sdiStatus = SdiStatus::Delivered;
                    }
                }

                $invoice = $this->createInvoice($body, $contact, $sequenceId, $status, $sdiStatus, $modelClass);

                // Self-invoice: persist document type and related invoice reference
                if ($isSelfInvoice) {
                    $this->applySelfInvoiceFields($body, $invoice);
                }

                $this->createInvoiceLines($body->DatiBeniServizi, $invoice);

                // Persist raw XML (including P7M signature if present) for disaster recovery
                $storageCategory = match ($category) {
                    'purchase' => 'purchase',
                    'self_invoice' => 'self-invoices',
                    default => 'sales',
                };

                $xmlFilename = $invoice->number
                    ? preg_replace('/[^A-Za-z0-9_\-.]/', '_', $invoice->number).'.xml'
                    : 'invoice-'.$invoice->id.'.xml';

                $xmlPath = $this->documentStorage->storeXml(
                    $xmlContent,
                    $storageCategory,
                    $invoice->date->year,
                    $xmlFilename,
                );

                $invoice->update(['xml_path' => $xmlPath]);

                $this->stats['invoices_imported']++;
            });
        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->errors[] = $e->getMessage();
            Log::error('XML invoice import error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Populate self-invoice-specific fields: document type (TD17, TD18, etc.)
     * and the related original invoice reference from DatiFattureCollegate.
     */
    protected function applySelfInvoiceFields(\SimpleXMLElement $body, Model $invoice): void
    {
        $datiGenerali = $body->DatiGenerali;
        $documentType = $this->extractText($datiGenerali->DatiGeneraliDocumento->TipoDocumento);

        $relatedNumber = null;
        $relatedDate = null;

        // DatiFattureCollegate holds the reference to the original supplier invoice
        if (isset($datiGenerali->DatiFattureCollegate)) {
            $relatedNumber = $this->extractText($datiGenerali->DatiFattureCollegate->IdDocumento);
            $relatedDate = $this->extractText($datiGenerali->DatiFattureCollegate->Data);
        }

        $invoice->update([
            'document_type' => $documentType,
            'related_invoice_number' => $relatedNumber,
            'related_invoice_date' => $relatedDate,
        ]);
    }

    /**
     * Parse XML content, handling namespaces and CDATA.
     */
    protected function parseXml(string $xmlContent): \SimpleXMLElement
    {
        // Detect and unwrap P7M (PKCS#7 CMS SignedData) envelopes.
        // SDI delivers some invoices as DER-encoded .xml.p7m files — the XML
        // is embedded inside the CMS structure and must be extracted first.
        if ($this->isP7m($xmlContent)) {
            $xmlContent = $this->extractXmlFromP7m($xmlContent);
        }

        // Remove namespace prefix so we can access elements directly
        $xmlContent = preg_replace('/<p:/', '<', $xmlContent);
        $xmlContent = preg_replace('/<\/p:/', '</', $xmlContent);

        // Remove digital signature block (ds:Signature)
        $xmlContent = preg_replace('/<ds:Signature[^>]*>.*<\/ds:Signature>/s', '', $xmlContent);

        $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new \RuntimeException('Impossibile parsare il file XML');
        }

        return $xml;
    }

    /**
     * Return true if the content looks like a DER-encoded PKCS#7 (P7M) file.
     * DER sequences start with byte 0x30 (ASN.1 SEQUENCE tag).
     */
    protected function isP7m(string $content): bool
    {
        return isset($content[0]) && ord($content[0]) === 0x30;
    }

    /**
     * Extract the raw XML payload from a PKCS#7 CMS SignedData envelope.
     * Uses the openssl CLI (always available on Linux servers).
     *
     * @throws \RuntimeException if extraction fails
     */
    protected function extractXmlFromP7m(string $p7mContent): string
    {
        $tmpInput = tempnam(sys_get_temp_dir(), 'p7m_in_');
        $tmpOutput = tempnam(sys_get_temp_dir(), 'p7m_out_');

        try {
            file_put_contents($tmpInput, $p7mContent);

            // -noverify skips certificate chain validation — we only need the payload
            $command = sprintf(
                'openssl smime -verify -in %s -inform DER -noverify -out %s 2>/dev/null',
                escapeshellarg($tmpInput),
                escapeshellarg($tmpOutput),
            );
            exec($command, result_code: $exitCode);

            if ($exitCode !== 0 || ! file_exists($tmpOutput)) {
                throw new \RuntimeException('Impossibile estrarre XML dal file P7M (CMS SignedData)');
            }

            $xml = file_get_contents($tmpOutput);
            if (empty($xml)) {
                throw new \RuntimeException('File P7M estratto ma il contenuto XML è vuoto');
            }

            return $xml;
        } finally {
            @unlink($tmpInput);
            @unlink($tmpOutput);
        }
    }

    /**
     * Find an existing contact by VAT number or create a new one.
     *
     * @param  bool  $isSupplier  When true, marks contact as supplier (purchase import)
     */
    protected function findOrCreateContact(\SimpleXMLElement $partyNode, bool $isSupplier = false): Contact
    {
        $anagrafica = $partyNode->DatiAnagrafici;
        $sede = $partyNode->Sede;

        $countryCode = $this->extractText($anagrafica->IdFiscaleIVA->IdPaese) ?? 'IT';
        $vatNumber = $this->extractText($anagrafica->IdFiscaleIVA->IdCodice);

        // Try to find existing contact by VAT number
        $contact = Contact::where('vat_number', $vatNumber)->first();

        if ($contact) {
            // Ensure the supplier flag is set when importing purchases
            if ($isSupplier && ! $contact->is_supplier) {
                $contact->update(['is_supplier' => true]);
            }

            return $contact;
        }

        // Build contact name from Denominazione or Nome+Cognome
        $name = $this->extractText($anagrafica->Anagrafica->Denominazione);
        if (! $name) {
            $nome = $this->extractText($anagrafica->Anagrafica->Nome) ?? '';
            $cognome = $this->extractText($anagrafica->Anagrafica->Cognome) ?? '';
            $name = trim("{$nome} {$cognome}");
        }

        $contact = Contact::create([
            'name' => $name,
            'vat_number' => $vatNumber,
            'tax_code' => $this->extractText($anagrafica->CodiceFiscale),
            'country_code' => $countryCode,
            'country' => $countryCode,
            'address' => $this->extractText($sede->Indirizzo),
            'postal_code' => $this->extractText($sede->CAP),
            'city' => $this->extractText($sede->Comune),
            'province' => $this->extractText($sede->Provincia),
            'is_customer' => ! $isSupplier,
            'is_supplier' => $isSupplier,
        ]);

        $this->stats['contacts_created']++;

        return $contact;
    }

    /**
     * Create the Invoice record from XML body data.
     */
    /**
     * @param  class-string<Model>  $modelClass  Invoice or PurchaseInvoice
     */
    protected function createInvoice(
        \SimpleXMLElement $body,
        Contact $contact,
        ?int $sequenceId,
        InvoiceStatus $status = InvoiceStatus::Sent,
        SdiStatus $sdiStatus = SdiStatus::Delivered,
        string $modelClass = Invoice::class
    ): Model {
        $datiGenerali = $body->DatiGenerali->DatiGeneraliDocumento;
        $datiPagamento = $body->DatiPagamento;

        // Withholding tax (Ritenuta d'acconto)
        $withholdingEnabled = isset($datiGenerali->DatiRitenuta);
        $withholdingPercent = null;
        $withholdingAmount = null;
        if ($withholdingEnabled) {
            $withholdingPercent = (float) $this->extractText($datiGenerali->DatiRitenuta->AliquotaRitenuta);
            $withholdingAmount = $this->euroToCents($this->extractText($datiGenerali->DatiRitenuta->ImportoRitenuta));
        }

        // Payment details
        $paymentMethod = null;
        $paymentTerms = null;
        $bankName = null;
        $bankIban = null;

        if (isset($datiPagamento)) {
            $paymentTerms = $this->extractText($datiPagamento->CondizioniPagamento);

            if (isset($datiPagamento->DettaglioPagamento)) {
                $dettaglio = $datiPagamento->DettaglioPagamento;
                $paymentMethod = $this->extractText($dettaglio->ModalitaPagamento);
                $bankName = $this->extractText($dettaglio->IstitutoFinanziario);
                $bankIban = $this->extractText($dettaglio->IBAN);
            }
        }

        // VAT payability from DatiRiepilogo
        $vatPayability = 'I';
        if (isset($body->DatiBeniServizi->DatiRiepilogo->EsigibilitaIVA)) {
            $vatPayability = $this->extractText($body->DatiBeniServizi->DatiRiepilogo->EsigibilitaIVA);
        }

        $number = $this->extractText($datiGenerali->Numero);
        $invoiceDate = $this->extractText($datiGenerali->Data);

        $year = $invoiceDate ? (int) substr($invoiceDate, 0, 4) : now()->year;

        // Reserve sequential number only when a sequence is provided (not needed for purchases)
        $sequentialNumber = null;
        if ($sequenceId) {
            $sequence = Sequence::find($sequenceId);
            $reserved = $sequence->reserveNextNumber($year);
            $sequentialNumber = $reserved['sequential_number'];
        }

        return $modelClass::create([
            'number' => $number,
            'sequence_id' => $sequenceId,
            'sequential_number' => $sequentialNumber,
            'fiscal_year' => $year,
            'date' => $invoiceDate,
            'contact_id' => $contact->id,
            'status' => $status,
            'sdi_status' => $sdiStatus,
            'payment_method' => $paymentMethod,
            'payment_terms' => $paymentTerms,
            'bank_name' => $bankName,
            'bank_iban' => $bankIban,
            'vat_payability' => $vatPayability,
            'withholding_tax_enabled' => $withholdingEnabled,
            'withholding_tax_percent' => $withholdingPercent,
            'withholding_tax_amount' => $withholdingAmount,
        ]);
    }

    /**
     * Create InvoiceLine records from XML line items.
     */
    protected function createInvoiceLines(\SimpleXMLElement $datiBeniServizi, Model $invoice): void
    {
        foreach ($datiBeniServizi->DettaglioLinee as $dettaglio) {
            $vatPercent = (float) $this->extractText($dettaglio->AliquotaIVA);
            $vatRate = $this->resolveVatRate($vatPercent);

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => $this->extractText($dettaglio->Descrizione),
                'quantity' => (float) $this->extractText($dettaglio->Quantita),
                'unit_price' => $this->euroToCents($this->extractText($dettaglio->PrezzoUnitario)),
                'vat_rate' => $vatRate->value,
                'total' => $this->euroToCents($this->extractText($dettaglio->PrezzoTotale)),
            ]);
        }
    }

    /**
     * Resolve a VatRate enum case by percentage.
     * Falls back to R22 (standard rate) for unknown percentages encountered in imported XML.
     */
    protected function resolveVatRate(float $percent): VatRate
    {
        foreach (VatRate::cases() as $case) {
            if ($case->percent() === $percent) {
                return $case;
            }
        }

        // Unknown percentage: use standard 22% as safe fallback
        Log::warning("InvoiceXmlImportService: unknown VAT percent {$percent}, falling back to R22");

        return VatRate::R22;
    }

    /**
     * Convert a euro amount string (e.g. "2000.00") to cents integer.
     */
    protected function euroToCents(?string $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round((float) $amount * 100);
    }

    /**
     * Safely extract text content from a SimpleXMLElement, handling CDATA.
     */
    protected function extractText(?\SimpleXMLElement $element): ?string
    {
        if ($element === null || ! isset($element[0])) {
            return null;
        }

        $text = trim((string) $element);

        return $text === '' ? null : $text;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function resetStats(): void
    {
        $this->stats = [
            'total' => 0,
            'invoices_imported' => 0,
            'contacts_created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $this->errors = [];
    }
}
