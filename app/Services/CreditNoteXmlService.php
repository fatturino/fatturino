<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Settings\CompanySettings;
use FatturaElettronicaPhp\FatturaElettronica\Address;
use FatturaElettronicaPhp\FatturaElettronica\Customer;
use FatturaElettronicaPhp\FatturaElettronica\Deduction;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocumentInstance;
use FatturaElettronicaPhp\FatturaElettronica\Discount;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DeductionType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DiscountType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DocumentType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TaxRegime;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TransmissionFormat;
use FatturaElettronicaPhp\FatturaElettronica\Enums\VatEligibility;
use FatturaElettronicaPhp\FatturaElettronica\Enums\VatNature;
use FatturaElettronicaPhp\FatturaElettronica\Line;
use FatturaElettronicaPhp\FatturaElettronica\PaymentDetails;
use FatturaElettronicaPhp\FatturaElettronica\PaymentInfo;
use FatturaElettronicaPhp\FatturaElettronica\RelatedDocument;
use FatturaElettronicaPhp\FatturaElettronica\Supplier;
use FatturaElettronicaPhp\FatturaElettronica\Total;

class CreditNoteXmlService
{
    public function __construct(
        protected CompanySettings $companySettings
    ) {
    }

    /**
     * Build SDI-compliant filename: CC + IdCodice + '_' + ProgressivoInvio + .xml
     */
    public function generateFileName(CreditNote $creditNote): string
    {
        $countryCode = $this->companySettings->company_country;
        $vatNumber = str_replace('IT', '', $this->companySettings->company_vat_number);
        $progressivo = str_pad($creditNote->id, 5, '0', STR_PAD_LEFT);

        return $countryCode . $vatNumber . '_' . $progressivo . '.xml';
    }

    public function generate(CreditNote $creditNote): string
    {
        $doc = new DigitalDocument;

        // 1. Transmission Data
        $countryCode = $this->companySettings->company_country;
        $vatNumber = $this->companySettings->company_vat_number;

        $doc->setCountryCode($countryCode);
        $doc->setSenderVatId(str_replace('IT', '', $vatNumber));
        $doc->setSendingId(str_pad($creditNote->id, 5, '0', STR_PAD_LEFT));
        $doc->setTransmissionFormat(TransmissionFormat::FPR12());
        $doc->setCustomerSdiCode($creditNote->contact->getSdiCodeForXml());

        // PEC: only for Italian customers
        if ($creditNote->contact->isItalian() && $creditNote->contact->pec) {
            $doc->setCustomerPec($creditNote->contact->pec);
        }

        // 2. Supplier (My Company — the one issuing the credit note)
        $supplier = new Supplier;
        $supplier->setCountryCode($countryCode);
        $supplier->setVatNumber(str_replace('IT', '', $vatNumber));
        $supplier->setFiscalCode($this->companySettings->company_tax_code);
        $supplier->setOrganization($this->companySettings->company_name);
        $supplier->setTaxRegime(new TaxRegime($this->companySettings->company_fiscal_regime));

        $supplierAddress = new Address;
        $supplierAddress->setStreet($this->companySettings->company_address);
        $supplierAddress->setZip($this->companySettings->company_postal_code);
        $supplierAddress->setCity($this->companySettings->company_city);
        $supplierAddress->setState($this->companySettings->company_province);
        $supplierAddress->setCountryCode($countryCode);
        $supplier->setAddress($supplierAddress);

        $doc->setSupplier($supplier);

        // 3. Customer (the recipient of the credit note)
        $customer = new Customer;
        $contact = $creditNote->contact;

        if ($contact->vat_number) {
            $customer->setCountryCode($contact->country ?? 'IT');
            if ($contact->isEU()) {
                $customer->setVatNumber($contact->getVatNumberClean());
            } else {
                $customer->setVatNumber('00000000000');
            }
        }

        if ($contact->isItalian() && $contact->tax_code) {
            $customer->setFiscalCode($contact->tax_code);
        }

        $customer->setOrganization($contact->name);

        $customerAddress = new Address;
        $customerAddress->setStreet($contact->address ?? 'N/A');
        $customerAddress->setZip($contact->getPostalCodeForXml());
        $customerAddress->setCity($contact->city ?? 'N/A');
        $customerAddress->setState($contact->getProvinceForXml());
        $customerAddress->setCountryCode($contact->country ?? 'IT');
        $customer->setAddress($customerAddress);

        $doc->setCustomer($customer);

        // 4. Body (DigitalDocumentInstance)
        $instance = new DigitalDocumentInstance;
        $instance->setDocumentType(DocumentType::TD04());
        $instance->setCurrency('EUR');
        $instance->setDocumentDate($creditNote->date);
        $instance->setDocumentNumber($creditNote->number);

        // Convert cents to euros
        $documentTotal = $creditNote->total_gross / 100;
        if ($creditNote->stamp_duty_applied) {
            $documentTotal += $creditNote->stamp_duty_amount / 100;
        }
        $instance->setDocumentTotal($documentTotal);
        $instance->addDescription($creditNote->notes ?? 'Nota di Credito n. ' . $creditNote->number);

        // Virtual stamp duty (DatiBollo)
        if ($creditNote->stamp_duty_applied) {
            $instance->setVirtualDuty('SI');
            $instance->setVirtualDutyAmount($creditNote->stamp_duty_amount / 100);
        }

        // Withholding tax (DatiRitenuta)
        if ($creditNote->withholding_tax_enabled && $creditNote->withholding_tax_amount) {
            $deduction = new Deduction;
            $deduction->setType(DeductionType::RT01());
            $deduction->setAmount($creditNote->withholding_tax_amount / 100);
            $deduction->setPercentage((float) $creditNote->withholding_tax_percent);
            $deduction->setDescription('A');
            $instance->addDeduction($deduction);
        }

        // DatiFattureCollegate — reference to the original invoice being credited
        // Recommended by SDI spec for TD04 to link the credit note to the original invoice
        if ($creditNote->related_invoice_number && $creditNote->related_invoice_date) {
            $relatedDocument = new RelatedDocument;
            $relatedDocument->setDocumentNumber($creditNote->related_invoice_number);
            $relatedDocument->setDocumentDate($creditNote->related_invoice_date->toDateTime());
            $instance->addRelatedInvoice($relatedDocument);
        }

        // Lines
        foreach ($creditNote->lines as $index => $line) {
            $lineItem = new Line;
            $lineItem->setNumber($index + 1);
            $lineItem->setDescription($line->description);
            $lineItem->setQuantity((float) $line->quantity);
            $lineItem->setUnit($line->unit_of_measure ?? $line->product?->unit ?? 'pz');
            $lineItem->setUnitPrice($line->unit_price / 100);
            $lineItem->setTotal($line->total / 100);

            if ($line->discount_percent) {
                $discount = new Discount;
                $discount->setType(DiscountType::SC());
                $discount->setPercentage((float) $line->discount_percent);
                $lineItem->addDiscount($discount);
            }

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
        foreach ($creditNote->lines as $line) {
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

        $vatEligibility = $creditNote->split_payment ? 'S' : ($creditNote->vat_payability ?? 'I');

        foreach ($summary as $data) {
            $total = new Total;
            $total->setTaxPercentage((float) $data['rate']);
            $total->setTotal((float) $data['taxable']);
            $total->setTaxAmount((float) $data['tax']);
            if ($data['nature']) {
                $total->setVatNature(new VatNature($data['nature']));
            }
            $total->setTaxType(new VatEligibility($vatEligibility));

            $instance->addTotal($total);
        }

        // Payment data (DatiPagamento) — included when a payment method is set
        if ($creditNote->payment_method) {
            $paymentInfo = new PaymentInfo;
            // Extract string value from enum if needed (PaymentTerm expects a string)
            $paymentTermsValue = $creditNote->payment_terms instanceof \BackedEnum
                ? $creditNote->payment_terms->value
                : ($creditNote->payment_terms ?? 'TP02');
            $paymentInfo->setTerms($paymentTermsValue);

            $paymentDetails = new PaymentDetails;
            $paymentDetails->setMethod($creditNote->payment_method);
            $paymentDetails->setAmount($documentTotal);

            if ($creditNote->due_date) {
                $paymentDetails->setDueDate($creditNote->due_date->toDateTime());
            }

            if ($creditNote->bank_iban) {
                $paymentDetails->setIban($creditNote->bank_iban);
            }
            if ($creditNote->bank_name) {
                $paymentDetails->setBankName($creditNote->bank_name);
            }

            $paymentInfo->addDetails($paymentDetails);
            $instance->addPaymentInformations($paymentInfo);
        }

        $doc->addDigitalDocumentInstance($instance);

        return $doc->serialize()->asXML();
    }
}
