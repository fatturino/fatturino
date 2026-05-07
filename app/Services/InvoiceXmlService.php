<?php

namespace App\Services;

use App\Models\Invoice;
use App\Services\Concerns\GeneratesSdiFilename;
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
use FatturaElettronicaPhp\FatturaElettronica\Fund;
use FatturaElettronicaPhp\FatturaElettronica\Line;
use FatturaElettronicaPhp\FatturaElettronica\PaymentDetails;
use FatturaElettronicaPhp\FatturaElettronica\PaymentInfo;
use FatturaElettronicaPhp\FatturaElettronica\Supplier;
use FatturaElettronicaPhp\FatturaElettronica\Total;

class InvoiceXmlService
{
    use GeneratesSdiFilename;

    public function __construct(
        protected CompanySettings $companySettings
    ) {}

    /**
     * Build SDI-compliant filename: CC + IdCodice + '_' + ProgressivoInvio + .xml
     * e.g. IT04826950166_00001.xml
     */
    public function generateFileName(Invoice $invoice): string
    {
        return $this->buildSdiFilename($this->companySettings, $invoice->id);
    }

    public function generate(Invoice $invoice): string
    {
        $doc = new DigitalDocument;

        // 1. Transmission Data
        $countryCode = $this->companySettings->company_country;
        $vatNumber = $this->companySettings->company_vat_number;

        $doc->setCountryCode($countryCode);
        $doc->setSenderVatId(str_replace('IT', '', $vatNumber));
        $doc->setSendingId(str_pad($invoice->id, 5, '0', STR_PAD_LEFT)); // Simple progressive
        $doc->setTransmissionFormat(TransmissionFormat::FPR12());
        $doc->setEmittingSystem('Fatturino');

        // SDI code: use helper method to get correct value based on customer country
        $doc->setCustomerSdiCode($invoice->contact->getSdiCodeForXml());

        // PEC: only for Italian customers
        if ($invoice->contact->isItalian() && $invoice->contact->pec) {
            $doc->setCustomerPec($invoice->contact->pec);
        }

        // 2. Supplier (My Company)
        $supplier = new Supplier;
        $supplier->setCountryCode($countryCode);
        $supplier->setVatNumber(str_replace('IT', '', $vatNumber));
        $supplier->setFiscalCode($this->companySettings->company_tax_code);
        $supplier->setOrganization($this->companySettings->company_name);

        if ($this->companySettings->company_email) {
            $supplier->setEmail($this->companySettings->company_email);
        }

        // Fiscal Regime
        $regime = $this->companySettings->company_fiscal_regime;
        $supplier->setTaxRegime(new TaxRegime($regime));

        $supplierAddress = new Address;
        $supplierAddress->setStreet($this->companySettings->company_address);
        $supplierAddress->setZip($this->companySettings->company_postal_code);
        $supplierAddress->setCity($this->companySettings->company_city);
        $supplierAddress->setState($this->companySettings->company_province);
        $supplierAddress->setCountryCode($countryCode);
        $supplier->setAddress($supplierAddress);

        $doc->setSupplier($supplier);

        // 3. Customer
        $customer = new Customer;
        $contact = $invoice->contact;

        // VAT number handling based on country
        if ($contact->vat_number) {
            $customer->setCountryCode($contact->country ?? 'IT');
            // For EU customers, use clean VAT number (without country prefix)
            // For non-EU customers, SDI requires 00000000000
            if ($contact->isEU()) {
                $customer->setVatNumber($contact->getVatNumberClean());
            } else {
                $customer->setVatNumber('00000000000');
            }
        }

        // Tax code: only for Italian customers with a valid-length codice fiscale (11 = company, 16 = individual)
        $taxCode = $contact->tax_code ?? '';
        if ($contact->isItalian() && strlen($taxCode) >= 11 && strlen($taxCode) <= 16) {
            $customer->setFiscalCode($taxCode);
        }

        $customer->setOrganization($contact->name);

        // Address: use helper methods for SDI-compliant values
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
        $instance->setDocumentType(new DocumentType($invoice->document_type ?? 'TD01'));
        $instance->setCurrency('EUR');
        $instance->setDocumentDate($invoice->date);
        $instance->setDocumentNumber($invoice->number);
        // Convert cents to euros; include stamp duty in document total
        $documentTotal = $invoice->total_gross / 100;
        if ($invoice->stamp_duty_applied) {
            $documentTotal += $invoice->stamp_duty_amount / 100;
        }
        $instance->setDocumentTotal($documentTotal);
        $instance->addDescription($invoice->notes ?? 'n. '.$invoice->number);

        // Virtual stamp duty (DatiBollo)
        if ($invoice->stamp_duty_applied) {
            $instance->setVirtualDuty('SI');
            $instance->setVirtualDutyAmount($invoice->stamp_duty_amount / 100);
        }

        // Withholding tax (DatiRitenuta)
        if ($invoice->withholding_tax_enabled && $invoice->withholding_tax_amount) {
            $deduction = new Deduction;
            $deduction->setType(DeductionType::RT01()); // RT01 = persona fisica
            $deduction->setAmount($invoice->withholding_tax_amount / 100);
            $deduction->setPercentage((float) $invoice->withholding_tax_percent);
            $deduction->setDescription('A'); // Causale pagamento: prestazioni lavoro autonomo
            $instance->addDeduction($deduction);
        }

        // Professional fund (DatiCassaPrevidenziale)
        if ($invoice->fund_enabled && $invoice->fund_amount) {
            $fund = new Fund;
            $fund->setType($invoice->fund_type);
            $fund->setPercentage((float) $invoice->fund_percent);
            $fund->setAmount($invoice->fund_amount / 100);
            $fund->setSubtotal($invoice->total_net / 100); // Imponibile (original net)

            $fund->setTaxPercentage((float) ($invoice->fund_vat_rate?->percent() ?? 0.0));

            if ($invoice->fund_has_deduction) {
                $fund->setDeduction(true);
            }

            if ($invoice->fund_vat_rate?->nature()) {
                $fund->setVatNature(new VatNature($invoice->fund_vat_rate->nature()));
            }

            $instance->addFund($fund);
        }

        // Lines
        foreach ($invoice->lines as $index => $line) {
            $lineItem = new Line;
            $lineItem->setNumber($index + 1);
            $lineItem->setDescription($line->description);
            $lineItem->setQuantity((float) $line->quantity);
            // Use explicit unit_of_measure if set, otherwise fallback to product default
            $lineItem->setUnit($line->unit_of_measure ?? $line->product?->unit ?? 'pz');
            // Convert cents to euros for XML
            $lineItem->setUnitPrice($line->unit_price / 100);
            $lineItem->setTotal($line->total / 100);

            // Discount (ScontoMaggiorazione) — unit_price stays pre-discount, total is post-discount
            if ($line->discount_percent) {
                $discount = new Discount;
                $discount->setType(DiscountType::SC()); // SC = sconto
                $discount->setPercentage((float) $line->discount_percent);
                $lineItem->addDiscount($discount);
            }

            // VAT
            if ($line->vat_rate) {
                $lineItem->setTaxPercentage((float) $line->vat_rate->percent());
                if ($line->vat_rate->nature()) {
                    $lineItem->setVatNature(new VatNature($line->vat_rate->nature()));
                }
            } else {
                $lineItem->setTaxPercentage(0.00);
            }

            $instance->addLine($lineItem);
        }

        // Totals (DatiRiepilogo)
        $summary = [];
        foreach ($invoice->lines as $line) {
            $key = ($line->vat_rate->percent() ?? 0).'_'.($line->vat_rate->nature() ?? '');
            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => $line->vat_rate->percent() ?? 0,
                    'nature' => $line->vat_rate->nature() ?? null,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            // Convert cents to euros
            $lineTotal = $line->total / 100;
            $summary[$key]['taxable'] += $lineTotal;
            $summary[$key]['tax'] += $lineTotal * (($line->vat_rate->percent() ?? 0) / 100);
        }

        // Include fund contribution (rivalsa) in the correct VAT bucket
        if ($invoice->fund_enabled && $invoice->fund_amount > 0) {
            $rate = $invoice->fund_vat_rate?->percent() ?? 0;
            $nature = $invoice->fund_vat_rate?->nature();
            $key = $rate.'_'.($nature ?? '');

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => $rate,
                    'nature' => $nature,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            $fundAmountEuros = $invoice->fund_amount / 100;
            $summary[$key]['taxable'] += $fundAmountEuros;
            $summary[$key]['tax'] += $fundAmountEuros * ($rate / 100);
        }

        // Split payment forces 'S' (Scissione); otherwise use the invoice vat_payability setting
        $vatEligibility = $invoice->split_payment ? 'S' : ($invoice->vat_payability ?? 'I');

        foreach ($summary as $data) {
            $total = new Total;
            $total->setTaxPercentage((float) $data['rate']);
            $total->setTotal((float) $data['taxable']); // Imponibile
            $total->setTaxAmount((float) $data['tax']); // Imposta
            if ($data['nature']) {
                $total->setVatNature(new VatNature($data['nature']));
            }
            $total->setTaxType(new VatEligibility($vatEligibility));

            $instance->addTotal($total);
        }

        // Payment data (DatiPagamento) — included when a payment method is set
        if ($invoice->payment_method) {
            $paymentInfo = new PaymentInfo;

            // Payment terms: TP01=immediate, TP02=deferred, TP03=advance — default TP02
            // Extract string value from enum if needed (PaymentTerm expects a string)
            $paymentTermsValue = $invoice->payment_terms instanceof \BackedEnum
                ? $invoice->payment_terms->value
                : ($invoice->payment_terms ?? 'TP02');
            $paymentInfo->setTerms($paymentTermsValue);

            $paymentDetails = new PaymentDetails;
            $paymentDetails->setMethod($invoice->payment_method->value);

            // Withholding tax reduces the amount actually paid by the customer
            $withholdingTax = $invoice->withholding_tax_enabled ? $invoice->withholding_tax_amount / 100 : 0.0;

            // Split payment: customer pays net + fund only (VAT goes directly to tax authority)
            $paymentAmount = $invoice->split_payment
                ? $documentTotal - ($invoice->total_vat / 100) - $withholdingTax
                : $documentTotal - $withholdingTax;
            $paymentDetails->setAmount($paymentAmount);

            // Due date from tracking field, if set
            if ($invoice->due_date) {
                $paymentDetails->setDueDate($invoice->due_date->toDateTime());
            }

            // Bank details for wire transfer methods
            if ($invoice->bank_iban) {
                $paymentDetails->setIban(str_replace(' ', '', $invoice->bank_iban));
            }
            if ($invoice->bank_name) {
                $paymentDetails->setBankName($invoice->bank_name);
            }

            $paymentInfo->addDetails($paymentDetails);
            $instance->addPaymentInformations($paymentInfo);
        }

        $doc->addDigitalDocumentInstance($instance);

        // Generate XML
        return trim($doc->serialize()->asXML());
    }
}
