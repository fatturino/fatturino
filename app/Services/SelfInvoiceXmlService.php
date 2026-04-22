<?php

namespace App\Services;

use App\Models\SelfInvoice;
use App\Settings\CompanySettings;
use FatturaElettronicaPhp\FatturaElettronica\Address;
use FatturaElettronicaPhp\FatturaElettronica\Customer;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocumentInstance;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DocumentType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\EmittingSubject;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TaxRegime;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TransmissionFormat;
use FatturaElettronicaPhp\FatturaElettronica\Enums\VatNature;
use FatturaElettronicaPhp\FatturaElettronica\Intermediary;
use FatturaElettronicaPhp\FatturaElettronica\Line;
use FatturaElettronicaPhp\FatturaElettronica\RelatedDocument;
use FatturaElettronicaPhp\FatturaElettronica\Supplier;
use FatturaElettronicaPhp\FatturaElettronica\Total;

class SelfInvoiceXmlService
{
    // Supported self-invoice document types
    public const DOCUMENT_TYPES = [
        'TD17' => 'TD17 — Servizi dall\'estero (integrazione/autofattura)',
        'TD18' => 'TD18 — Acquisto beni intracomunitari',
        'TD19' => 'TD19 — Acquisto beni ex art.17 c.2 DPR 633/72',
        'TD28' => 'TD28 — Acquisti da San Marino con IVA',
        'TD29' => 'TD29 — Comunicazione per omessa o irregolare fatturazione',
    ];

    public function __construct(
        protected CompanySettings $companySettings
    ) {
    }

    /**
     * Build SDI-compliant filename: CC + IdCodice + '_' + ProgressivoInvio + .xml
     */
    public function generateFileName(SelfInvoice $invoice): string
    {
        $countryCode = $this->companySettings->company_country;
        $vatNumber = str_replace('IT', '', $this->companySettings->company_vat_number);
        $progressivo = str_pad($invoice->id, 5, '0', STR_PAD_LEFT);

        return $countryCode . $vatNumber . '_' . $progressivo . '.xml';
    }

    public function generate(SelfInvoice $invoice): string
    {
        $doc = new DigitalDocument;

        $companyCountry = $this->companySettings->company_country;
        $companyVat = $this->companySettings->company_vat_number;

        // 1. Transmission Data — I am the transmitter, and also the recipient (self-invoice)
        $doc->setCountryCode($companyCountry);
        $doc->setSenderVatId(str_replace('IT', '', $companyVat));
        $doc->setSendingId(str_pad($invoice->id, 5, '0', STR_PAD_LEFT));
        $doc->setTransmissionFormat(TransmissionFormat::FPR12());

        // For self-invoices, the destination is my own SDI code (sending to myself)
        $mySdiCode = $this->companySettings->company_sdi_code ?: '0000000';
        $doc->setCustomerSdiCode($mySdiCode);

        // SoggettoEmittente = CC (the buyer/cessionario is issuing the document)
        $doc->setEmittingSubject(EmittingSubject::CC());

        // 2. Supplier (Cedente/Prestatore) = the FOREIGN SUPPLIER (the contact)
        // In a self-invoice, the supplier is the foreign entity who sold us goods/services
        $supplier = new Supplier;
        $foreignContact = $invoice->contact;

        $supplier->setCountryCode($foreignContact->country ?? 'IT');

        // VAT number: in autofattura, always use the real foreign supplier's tax ID
        // (the 00000000000 convention only applies to CessionarioCommittente in sales invoices)
        if ($foreignContact->vat_number) {
            $supplier->setVatNumber($foreignContact->getVatNumberClean());
        }

        $supplier->setOrganization($foreignContact->name);

        // TaxRegime is required on the supplier — use a generic regime
        // For self-invoices this field refers to the foreign supplier's regime
        $supplier->setTaxRegime(new TaxRegime('RF18')); // RF18 = Other (for foreign entities)

        $supplierAddress = new Address;
        $supplierAddress->setStreet($foreignContact->address ?? 'N/A');
        $supplierAddress->setZip($foreignContact->getPostalCodeForXml());
        $supplierAddress->setCity($foreignContact->city ?? 'N/A');
        // Provincia: only for Italian entities, omit for foreign suppliers per SDI spec
        if ($foreignContact->isItalian()) {
            $supplierAddress->setState($foreignContact->province ?? '');
        }
        $supplierAddress->setCountryCode($foreignContact->country ?? 'IT');
        $supplier->setAddress($supplierAddress);

        $doc->setSupplier($supplier);

        // 3. Customer (Cessionario/Committente) = MY COMPANY
        // In a self-invoice, the buyer is my company (who is also issuing it)
        $customer = new Customer;
        $customer->setCountryCode($companyCountry);
        $customer->setVatNumber(str_replace('IT', '', $companyVat));
        $customer->setFiscalCode($this->companySettings->company_tax_code);
        $customer->setOrganization($this->companySettings->company_name);

        $customerAddress = new Address;
        $customerAddress->setStreet($this->companySettings->company_address);
        $customerAddress->setZip($this->companySettings->company_postal_code);
        $customerAddress->setCity($this->companySettings->company_city);
        $customerAddress->setState($this->companySettings->company_province);
        $customerAddress->setCountryCode($companyCountry);
        $customer->setAddress($customerAddress);

        $doc->setCustomer($customer);

        // 4. Third Intermediary (TerzoIntermediarioOSoggettoEmittente)
        // Required when SoggettoEmittente = CC: the buyer is issuing the document
        $intermediary = new Intermediary;
        $intermediary->setCountryCode($companyCountry);
        $intermediary->setVatNumber(str_replace('IT', '', $companyVat));
        $intermediary->setFiscalCode($this->companySettings->company_tax_code);
        $intermediary->setOrganization($this->companySettings->company_name);
        $doc->setIntermediary($intermediary);

        // 5. Body (DigitalDocumentInstance)
        $instance = new DigitalDocumentInstance;
        $instance->setDocumentType($this->resolveDocumentType($invoice->document_type));
        $instance->setCurrency('EUR');
        $instance->setDocumentDate($invoice->date);
        $instance->setDocumentNumber($invoice->number);
        $instance->setDocumentTotal($invoice->total_gross / 100);
        $instance->addDescription($invoice->notes ?? 'Autofattura n. ' . $invoice->number);

        // DatiFattureCollegate — mandatory for self-invoices: reference to original foreign invoice
        if ($invoice->related_invoice_number && $invoice->related_invoice_date) {
            $relatedDocument = new RelatedDocument;
            $relatedDocument->setDocumentNumber($invoice->related_invoice_number);
            $relatedDocument->setDocumentDate($invoice->related_invoice_date->toDateTime());
            $instance->addRelatedInvoice($relatedDocument);
        }

        // Lines
        foreach ($invoice->lines as $index => $line) {
            $lineItem = new Line;
            $lineItem->setNumber($index + 1);
            $lineItem->setDescription($line->description);
            $lineItem->setQuantity((float) $line->quantity);
            // Use explicit unit_of_measure if set, otherwise fallback to product default
            $lineItem->setUnit($line->unit_of_measure ?? $line->product?->unit ?? 'pz');
            $lineItem->setUnitPrice($line->unit_price / 100);
            $lineItem->setTotal($line->total / 100);

            if ($line->vatRate) {
                $lineItem->setTaxPercentage((float) $line->vatRate->percent);
                if ($line->vatRate->nature) {
                    $lineItem->setVatNature(new VatNature($line->vatRate->nature));
                }
            } else {
                $lineItem->setTaxPercentage(0.00);
            }

            $instance->addLine($lineItem);
        }

        // Totals (DatiRiepilogo) — grouped by VAT rate and nature
        $summary = [];
        foreach ($invoice->lines as $line) {
            $key = ($line->vatRate->percent ?? 0) . '_' . ($line->vatRate->nature ?? '');
            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate'    => $line->vatRate->percent ?? 0,
                    'nature'  => $line->vatRate->nature ?? null,
                    'taxable' => 0,
                    'tax'     => 0,
                ];
            }

            $lineTotal = $line->total / 100;
            $summary[$key]['taxable'] += $lineTotal;
            $summary[$key]['tax'] += $lineTotal * (($line->vatRate->percent ?? 0) / 100);
        }

        foreach ($summary as $data) {
            $total = new Total;
            $total->setTaxPercentage((float) $data['rate']);
            $total->setTotal((float) $data['taxable']);
            $total->setTaxAmount((float) $data['tax']);
            if ($data['nature']) {
                $total->setVatNature(new VatNature($data['nature']));
            }
            $instance->addTotal($total);
        }

        $doc->addDigitalDocumentInstance($instance);

        return $doc->serialize()->asXML();
    }

    /**
     * Map the stored document_type string to the library's DocumentType enum.
     */
    private function resolveDocumentType(string $documentType): DocumentType
    {
        return match ($documentType) {
            'TD17' => DocumentType::TD17(),
            'TD18' => DocumentType::TD18(),
            'TD19' => DocumentType::TD19(),
            'TD28' => DocumentType::TD28(),
            'TD29' => DocumentType::TD29(),
            default => DocumentType::TD17(),
        };
    }
}
