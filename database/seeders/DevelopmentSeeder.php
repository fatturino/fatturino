<?php

namespace Database\Seeders;

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Models\User;
use App\Enums\VatRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Seed development and testing data.
     * WARNING: This seeder is for development only - DO NOT run in production!
     */
    public function run(): void
    {
        $this->seedCompanySettings();
        $this->seedUser();

        $contacts = $this->seedContacts();
        $products = $this->seedProducts();

        $this->seedSalesInvoices($contacts, $products);
        $this->seedPurchaseInvoices($contacts, $products);
        $this->seedSelfInvoices($contacts, $products);
    }

    /**
     * Admin user for development login
     */
    private function seedUser(): void
    {
        User::firstOrCreate(
            ['email' => 'utente@fatturino.it'],
            [
                'name' => 'Utente Fatturino',
                'password' => Hash::make('password'),
            ]
        );
    }

    /**
     * A varied set of contacts: Italian customers, EU customer, non-EU customer, Italian suppliers.
     * Covers different invoice scenarios (SDI code, PEC, withholding tax, foreign).
     *
     * @return array<string, Contact>
     */
    private function seedContacts(): array
    {
        // Italian customer with SDI code
        $customer1 = Contact::firstOrCreate(
            ['vat_number' => 'IT12345678903'],
            [
                'is_customer' => true,
                'name' => 'Mario Rossi S.r.l.',
                'tax_code' => '12345678903',
                'address' => 'Via Roma 10',
                'city' => 'Milano',
                'postal_code' => '20100',
                'province' => 'MI',
                'country' => 'IT',
                'country_code' => 'IT',
                'sdi_code' => 'M5UXCR1',
                'pec' => 'mario.rossi@pec.it',
                'email' => 'info@mariorossi.it',
            ]
        );

        // Italian customer using PEC fallback (sdi_code = 0000000)
        $customer2 = Contact::firstOrCreate(
            ['vat_number' => 'IT55544433324'],
            [
                'is_customer' => true,
                'name' => 'Bianchi & Verdi S.n.c.',
                'tax_code' => '55544433324',
                'address' => 'Corso Vittorio Emanuele 45',
                'city' => 'Roma',
                'postal_code' => '00100',
                'province' => 'RM',
                'country' => 'IT',
                'country_code' => 'IT',
                'sdi_code' => '0000000',
                'pec' => 'bianchi.verdi@legalmail.it',
                'email' => 'info@bianchiverdi.it',
            ]
        );

        // Italian individual / freelancer (subject to withholding tax - ritenuta d'acconto)
        $customer3 = Contact::firstOrCreate(
            ['vat_number' => 'IT77788899901'],
            [
                'is_customer' => true,
                'name' => 'Luca Ferrari',
                'tax_code' => 'FRRLCU80A01F205N',
                'address' => 'Via Garibaldi 3',
                'city' => 'Firenze',
                'postal_code' => '50100',
                'province' => 'FI',
                'country' => 'IT',
                'country_code' => 'IT',
                'sdi_code' => 'T04ZHR3',
                'email' => 'luca.ferrari@gmail.com',
            ]
        );

        // EU customer (Germany) - for intra-community invoices (N3.2)
        $customer4 = Contact::firstOrCreate(
            ['vat_number' => 'DE123456789'],
            [
                'is_customer' => true,
                'is_supplier' => true, // Also used as EU supplier for self-invoices
                'name' => 'Deutsches Unternehmen GmbH',
                'address' => 'Hauptstraße 100',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'country' => 'DE',
                'country_code' => 'DE',
                'email' => 'kontakt@deutsches-unternehmen.de',
            ]
        );

        // Non-EU customer (USA) - for export invoices (N3.1)
        $customer5 = Contact::firstOrCreate(
            ['vat_number' => 'US-EIN-123456789'],
            [
                'is_customer' => true,
                'name' => 'American Company Inc.',
                'address' => '123 Main Street',
                'city' => 'New York',
                'postal_code' => '10001',
                'country' => 'US',
                'country_code' => 'US',
                'email' => 'billing@americancompany.com',
            ]
        );

        // Italian supplier (company)
        $supplier1 = Contact::firstOrCreate(
            ['vat_number' => 'IT09876543217'],
            [
                'is_supplier' => true,
                'name' => 'Forniture Elettriche S.p.A.',
                'tax_code' => '09876543217',
                'address' => 'Via delle Industrie 5',
                'city' => 'Torino',
                'postal_code' => '10100',
                'province' => 'TO',
                'country' => 'IT',
                'country_code' => 'IT',
                'sdi_code' => '0000000',
                'pec' => 'forniture@pec.it',
                'email' => 'amministrazione@forniture.it',
            ]
        );

        // Italian supplier (professional studio - applies withholding tax on its invoices)
        $supplier2 = Contact::firstOrCreate(
            ['vat_number' => 'IT33322211104'],
            [
                'is_supplier' => true,
                'name' => 'Studio Legale Esposito',
                'tax_code' => 'SPSGPP75M12F839B',
                'address' => 'Piazza San Marco 7',
                'city' => 'Napoli',
                'postal_code' => '80100',
                'province' => 'NA',
                'country' => 'IT',
                'country_code' => 'IT',
                'sdi_code' => 'KRRH6B9',
                'pec' => 'studio.esposito@pec.it',
                'email' => 'info@studioesposito.it',
            ]
        );

        return compact('customer1', 'customer2', 'customer3', 'customer4', 'customer5', 'supplier1', 'supplier2');
    }

    /**
     * Products with varied VAT rates to exercise all calculation paths.
     *
     * @return array<string, Product|null>
     */
    private function seedProducts(): array
    {
        $consulting = Product::firstOrCreate(
            ['name' => 'Consulenza Informatica'],
            [
                'description' => 'Sviluppo software e assistenza tecnica',
                'price' => 50000, // €500.00
                'unit' => 'ora',
                'vat_rate' => VatRate::R22->value,
            ]
        );

        $hosting = Product::firstOrCreate(
            ['name' => 'Hosting Web Annuale'],
            [
                'description' => 'Spazio web, dominio e certificato SSL',
                'price' => 12000, // €120.00
                'unit' => 'anno',
                'vat_rate' => VatRate::R22->value,
            ]
        );

        $support = Product::firstOrCreate(
            ['name' => 'Assistenza Tecnica'],
            [
                'description' => 'Supporto on-site e da remoto',
                'price' => 8000, // €80.00
                'unit' => 'ora',
                'vat_rate' => VatRate::R10->value,
            ]
        );

        $training = Product::firstOrCreate(
            ['name' => 'Formazione Aziendale'],
            [
                'description' => 'Corso di formazione in aula',
                'price' => 25000, // €250.00
                'unit' => 'giorno',
                'vat_rate' => VatRate::R22->value,
            ]
        );

        // Exempt product (N4) - triggers stamp duty obligation above €77.47
        $exemptService = Product::firstOrCreate(
            ['name' => 'Consulenza Finanziaria'],
            [
                'description' => 'Analisi e consulenza su investimenti finanziari',
                'price' => 30000, // €300.00
                'unit' => 'ora',
                'vat_rate' => VatRate::N4->value,
            ]
        );

        // Non-taxable product for EU customers (N3.2)
        $euProduct = Product::firstOrCreate(
            ['name' => 'Licenza Software (UE)'],
            [
                'description' => 'Licenza software per clienti intracomunitari',
                'price' => 100000, // €1000.00
                'unit' => 'licenza',
                'vat_rate' => VatRate::N3_2->value,
            ]
        );

        // Non-subject product for non-EU customers (N2.1)
        $exportProduct = Product::firstOrCreate(
            ['name' => 'Servizio Digitale (Extra-UE)'],
            [
                'description' => 'Servizio digitale erogato a clienti extra-comunitari',
                'price' => 75000, // €750.00
                'unit' => 'progetto',
                'vat_rate' => VatRate::N2_1->value,
            ]
        );

        return compact('consulting', 'hosting', 'support', 'training', 'exemptService', 'euProduct', 'exportProduct');
    }

    /**
     * Sales invoices covering all major features:
     * - Simple / multi-line
     * - Withholding tax (ritenuta d'acconto)
     * - Stamp duty (marca da bollo)
     * - Fund contribution (rivalsa previdenziale)
     * - EU and non-EU customers
     * - SDI statuses (sent, delivered, rejected)
     * - Bank and payment details
     */
    private function seedSalesInvoices(array $contacts, array $products): void
    {
        $year = now()->year;
        $sequence = Sequence::where('type', 'electronic_invoice')->first();

        if (! $sequence) {
            return;
        }

        // Invoice 1: simple, single line at 22%, with bank details
        $inv1 = $this->createSalesInvoice($sequence, $contacts['customer1'], $year, [
            'date' => now()->subMonths(3)->startOfMonth(),
            'payment_method' => 'MP05', // Bank transfer
            'bank_name' => 'Intesa Sanpaolo',
            'bank_iban' => 'IT60X0542811101000000123456',
            'notes' => 'Pagamento entro 30 giorni dalla data fattura.',
        ]);
        $this->addLine($inv1, $products['consulting'], 2);

        // Invoice 2: multi-line with mixed VAT rates (22% + 10%)
        $inv2 = $this->createSalesInvoice($sequence, $contacts['customer2'], $year, [
            'date' => now()->subMonths(2)->startOfMonth(),
            'payment_method' => 'MP05',
        ]);
        $this->addLine($inv2, $products['consulting'], 3);
        $this->addLine($inv2, $products['hosting'], 1);
        $this->addLine($inv2, $products['support'], 2);

        // Invoice 3: withholding tax 20% (ritenuta d'acconto for professional services)
        $inv3 = $this->createSalesInvoice($sequence, $contacts['customer3'], $year, [
            'date' => now()->subMonths(2)->addDays(15),
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => 20.00,
            'payment_method' => 'MP05',
            'notes' => 'Ritenuta d\'acconto 20% a carico del committente ex art. 25 DPR 600/73.',
        ]);
        $this->addLine($inv3, $products['consulting'], 4);

        // Invoice 4: stamp duty €2.00 (marca da bollo on exempt invoices above €77.47)
        $inv4 = $this->createSalesInvoice($sequence, $contacts['customer1'], $year, [
            'date' => now()->subMonths(2)->addDays(20),
            'stamp_duty_applied' => true,
            'stamp_duty_amount' => 200, // €2.00 in cents
            'notes' => 'Imposta di bollo assolta in modo virtuale ai sensi del DM 17/06/2014.',
        ]);
        $product4 = $products['exemptService'] ?? $products['consulting'];
        $this->addLine($inv4, $product4, 2);

        // Invoice 5: fund contribution (rivalsa INPS 4% with 22% VAT on top)
        $inv5 = $this->createSalesInvoice($sequence, $contacts['customer2'], $year, [
            'date' => now()->subMonth()->startOfMonth(),
            'fund_enabled' => true,
            'fund_type' => 'INPS',
            'fund_percent' => 4.00,
            'fund_vat_rate' => VatRate::R22->value,
            'fund_has_deduction' => false,
        ]);
        $this->addLine($inv5, $products['consulting'], 5);

        // Invoice 6: EU customer, intra-community supply (N3.2, zero VAT)
        $inv6 = $this->createSalesInvoice($sequence, $contacts['customer4'], $year, [
            'date' => now()->subMonth()->addDays(5),
        ]);
        $product6 = $products['euProduct'] ?? $products['consulting'];
        $this->addLine($inv6, $product6, 2);

        // Invoice 7: non-EU customer, export (N2.1, zero VAT)
        $inv7 = $this->createSalesInvoice($sequence, $contacts['customer5'], $year, [
            'date' => now()->subMonth()->addDays(10),
        ]);
        $product7 = $products['exportProduct'] ?? $products['consulting'];
        $this->addLine($inv7, $product7, 1);

        // Invoice 8: delivered via SDI (RC - Ricevuta di Consegna)
        $inv8 = $this->createSalesInvoice($sequence, $contacts['customer1'], $year, [
            'date' => now()->subDays(25),
            'sdi_status' => SdiStatus::Delivered,
            'sdi_uuid' => fake()->uuid(),
            'sdi_sent_at' => now()->subDays(24),
            'payment_method' => 'MP05',
            'bank_iban' => 'IT60X0542811101000000123456',
        ]);
        $this->addLine($inv8, $products['training'], 2);

        // Invoice 9: sent to SDI but not yet delivered (pending)
        $inv9 = $this->createSalesInvoice($sequence, $contacts['customer2'], $year, [
            'date' => now()->subDays(15),
            'sdi_status' => SdiStatus::Sent,
            'sdi_uuid' => fake()->uuid(),
            'sdi_sent_at' => now()->subDays(14),
        ]);
        $this->addLine($inv9, $products['hosting'], 2);
        $this->addLine($inv9, $products['support'], 3);

        // Invoice 10: rejected by SDI (NS - Notifica di Scarto) - editable again
        $inv10 = $this->createSalesInvoice($sequence, $contacts['customer3'], $year, [
            'date' => now()->subDays(10),
            'sdi_status' => SdiStatus::Rejected,
            'sdi_uuid' => fake()->uuid(),
            'sdi_sent_at' => now()->subDays(9),
            'sdi_message' => 'Errore 00400: il valore del campo TipoDocumento non è corretto.',
        ]);
        $this->addLine($inv10, $products['consulting'], 6);

        // Invoice 11: draft with training + consulting (multi-line, same VAT)
        $inv11 = $this->createSalesInvoice($sequence, $contacts['customer1'], $year, [
            'date' => now()->subDays(5),
            'payment_method' => 'MP01', // Cash
        ]);
        $this->addLine($inv11, $products['training'], 1);
        $this->addLine($inv11, $products['consulting'], 8);

        // Invoice 12: recent draft with split payment (scissione dei pagamenti - art. 17-ter)
        $inv12 = $this->createSalesInvoice($sequence, $contacts['customer2'], $year, [
            'date' => now()->subDays(2),
            'split_payment' => true,
            'vat_payability' => 'S',
            'payment_method' => 'MP05',
            'bank_iban' => 'IT60X0542811101000000123456',
            'notes' => 'Operazione soggetta a scissione dei pagamenti ex art. 17-ter DPR 633/72.',
        ]);
        $this->addLine($inv12, $products['hosting'], 3);
        $this->addLine($inv12, $products['support'], 5);
    }

    /**
     * Purchase invoices (fatture passive) from Italian suppliers.
     */
    private function seedPurchaseInvoices(array $contacts, array $products): void
    {
        $year = now()->year;
        $sequence = Sequence::where('type', 'purchase')->first();

        if (! $sequence) {
            return;
        }

        // Purchase 1: simple from company supplier
        $pInv1 = $this->createPurchaseInvoice($sequence, $contacts['supplier1'], $year, [
            'date' => now()->subMonths(2),
            'number' => 'FT-2026-001', // Supplier's invoice number
        ]);
        $this->addLineToInvoice($pInv1, VatRate::R10, 'Fornitura materiale elettrico', 'pz', 50000, 3);

        // Purchase 2: from professional studio with withholding tax
        $pInv2 = $this->createPurchaseInvoice($sequence, $contacts['supplier2'], $year, [
            'date' => now()->subMonth(),
            'number' => 'SLES-2026-0042', // Supplier's invoice number
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => 20.00,
        ]);
        $this->addLineToInvoice($pInv2, VatRate::R22, 'Consulenza legale contrattuale', 'ora', 20000, 5);

        // Purchase 3: recent from company supplier
        $pInv3 = $this->createPurchaseInvoice($sequence, $contacts['supplier1'], $year, [
            'date' => now()->subDays(5),
            'number' => 'FT-2026-089', // Supplier's invoice number
        ]);
        $this->addLineToInvoice($pInv3, VatRate::R22, 'Componenti hardware', 'pz', 35000, 2);
    }

    /**
     * Self-invoices (autofatture TD17/TD18/TD19) for reverse charge scenarios.
     */
    private function seedSelfInvoices(array $contacts, array $products): void
    {
        $year = now()->year;
        $sequence = Sequence::where('type', 'self_invoice')->first();

        if (! $sequence) {
            return;
        }

        // Self-invoice TD17: service received from EU supplier (reverse charge)
        $selfInv1 = $this->createSelfInvoice($sequence, $contacts['customer4'], $year, [
            'date' => now()->subMonth(),
            'document_type' => 'TD17',
            'related_invoice_number' => 'RE-2026-0042',
            'related_invoice_date' => now()->subMonth()->subDays(5),
        ]);

        // Reverse charge: VAT rate N6.9 on the purchase side
        $this->addLineToSelfInvoice($selfInv1, VatRate::N6_9, 'Servizio di consulenza da fornitore UE', 'progetto', 80000, 1);

        // Self-invoice TD18: intra-community goods purchase (reverse charge)
        $selfInv2 = $this->createSelfInvoice($sequence, $contacts['customer4'], $year, [
            'date' => now()->subDays(20),
            'document_type' => 'TD18',
            'related_invoice_number' => 'INV-2026-0010',
            'related_invoice_date' => now()->subDays(25),
        ]);
        $this->addLineToSelfInvoice($selfInv2, VatRate::R22, 'Acquisto intracomunitario hardware', 'pz', 60000, 4);
    }

    // ─── Helper: create a sales invoice and return it ─────────────────────────

    private function createSalesInvoice(Sequence $sequence, Contact $contact, int $year, array $attributes): Invoice
    {
        $numberData = $sequence->reserveNextNumber($year);

        return Invoice::create(array_merge([
            'sequence_id' => $sequence->id,
            'sequential_number' => $numberData['sequential_number'],
            'number' => $numberData['formatted_number'],
            'contact_id' => $contact->id,
            'date' => now(),
            'fiscal_year' => $year,
            'status' => 'draft',
            'payment_method' => 'MP05',
            'vat_payability' => 'I',
        ], $attributes));
    }

    // ─── Helper: create a purchase invoice and return it ──────────────────────

    private function createPurchaseInvoice(Sequence $sequence, Contact $contact, int $year, array $attributes): PurchaseInvoice
    {
        $numberData = $sequence->reserveNextNumber($year);

        return PurchaseInvoice::create(array_merge([
            'sequence_id' => $sequence->id,
            'sequential_number' => $numberData['sequential_number'],
            'number' => $numberData['formatted_number'],
            'contact_id' => $contact->id,
            'date' => now(),
            'fiscal_year' => $year,
            'status' => 'draft',
            'vat_payability' => 'I',
        ], $attributes));
    }

    // ─── Helper: create a self-invoice and return it ───────────────────────────

    private function createSelfInvoice(Sequence $sequence, Contact $contact, int $year, array $attributes): SelfInvoice
    {
        $numberData = $sequence->reserveNextNumber($year);

        return SelfInvoice::create(array_merge([
            'sequence_id' => $sequence->id,
            'sequential_number' => $numberData['sequential_number'],
            'number' => $numberData['formatted_number'],
            'contact_id' => $contact->id,
            'date' => now(),
            'fiscal_year' => $year,
            'status' => 'draft',
            'vat_payability' => 'I',
        ], $attributes));
    }

    // ─── Helper: add a line from a Product model ──────────────────────────────

    private function addLine(Invoice|PurchaseInvoice|SelfInvoice $invoice, Product $product, float $quantity): void
    {
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => $product->description,
            'quantity' => $quantity,
            'unit_of_measure' => $product->unit,
            'unit_price' => $product->price,
            'total' => (int) round($product->price * $quantity),
            'vat_rate' => $product->vat_rate?->value,
        ]);
    }

    // ─── Helper: add a custom line to a purchase invoice ──────────────────────

    private function addLineToInvoice(PurchaseInvoice $invoice, VatRate $vatRate, string $description, string $unitOfMeasure, int $unitPrice, float $quantity): void
    {
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_of_measure' => $unitOfMeasure,
            'unit_price' => $unitPrice,
            'total' => (int) round($unitPrice * $quantity),
            'vat_rate' => $vatRate->value,
        ]);
    }

    // ─── Helper: add a custom line to a self-invoice ──────────────────────────

    private function addLineToSelfInvoice(SelfInvoice $invoice, VatRate $vatRate, string $description, string $unitOfMeasure, int $unitPrice, float $quantity): void
    {
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_of_measure' => $unitOfMeasure,
            'unit_price' => $unitPrice,
            'total' => (int) round($unitPrice * $quantity),
            'vat_rate' => $vatRate->value,
        ]);
    }

    /**
     * Seed company settings from config file.
     * Skipped if settings already exist.
     */
    private function seedCompanySettings(): void
    {
        $existingSettings = \DB::table('settings')
            ->where('group', 'company')
            ->exists();

        if ($existingSettings) {
            return;
        }

        $settings = [
            'company_name' => config('company.name'),
            'company_vat_number' => config('company.vat_number'),
            'company_tax_code' => config('company.tax_code'),
            'company_address' => config('company.address'),
            'company_city' => config('company.city'),
            'company_postal_code' => config('company.postal_code'),
            'company_province' => config('company.province'),
            'company_country' => config('company.country'),
            'company_pec' => config('company.pec'),
            'company_sdi_code' => config('company.sdi_code'),
            'company_fiscal_regime' => config('company.fiscal_regime'),
        ];

        $now = now();
        foreach ($settings as $name => $value) {
            \DB::table('settings')->insert([
                'group' => 'company',
                'name' => $name,
                'locked' => 0,
                'payload' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
