<?php

namespace Database\Seeders;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoModeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDemoUser();
        $this->seedCompanyAndInvoiceSettings();
        $this->resetOperationalData();
        $this->seedContacts();
        $this->seedSalesInvoicesFor2025And2026();
        $this->seedProformaAndConvertedInvoices();
        $this->seedSelfInvoices();
        $this->seedCreditNotes();
    }

    private function seedDemoUser(): void
    {
        User::updateOrCreate(
            ['email' => config('demo.email', 'demo@fatturino.it')],
            [
                'name' => 'Account Demo',
                'password' => Hash::make(config('demo.password', 'demo')),
                'is_admin' => true,
            ]
        );
    }

    private function seedCompanyAndInvoiceSettings(): void
    {
        $company = app(CompanySettings::class);
        $company->company_name = 'Mario Rossi - Consulenza IT';
        $company->company_vat_number = 'IT51661350317';
        $company->company_tax_code = 'RSSMRA85M01F205X';
        $company->company_address = 'Via Torino 21';
        $company->company_city = 'Milano';
        $company->company_postal_code = '20121';
        $company->company_province = 'MI';
        $company->company_country = 'IT';
        $company->company_email = 'mario.rossi@studio-demo.it';
        $company->company_pec = 'mario.rossi@pec.it';
        $company->company_sdi_code = 'M5UXCR1';
        $company->company_fiscal_regime = 'RF19';
        $company->save();

        $salesSequence = $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}');
        $proformaSequence = $this->resolveSequence('ProForma', 'proforma', 'PRO-{SEQ}');
        $selfInvoiceSequence = $this->resolveSequence('Autofatture', 'self_invoice', 'AUTO-{SEQ}');
        $creditNoteSequence = $this->resolveSequence('Note di Credito', 'credit_note', 'NC-{SEQ}');

        $invoice = app(InvoiceSettings::class);
        $invoice->default_sequence_sales = $salesSequence->id;
        $invoice->default_sequence_proforma = $proformaSequence->id;
        $invoice->default_sequence_self_invoice = $selfInvoiceSequence->id;
        $invoice->default_sequence_credit_notes = $creditNoteSequence->id;
        $invoice->default_vat_rate = VatRate::R22;
        $invoice->default_payment_method = 'MP05';
        $invoice->default_payment_terms = 'TP02';
        $invoice->default_notes = 'Grazie per la fiducia.';
        $invoice->withholding_tax_enabled = true;
        $invoice->withholding_tax_percent = '20.00';
        $invoice->yearly_numbering_reset = true;
        $invoice->save();

    }

    private function resetOperationalData(): void
    {
        DB::table('payments')->delete();
        DB::table('ei_outbound_logs')->delete();
        DB::table('ei_inbound_logs')->delete();
        DB::table('fiscal_documents_lines')->delete();
        DB::table('fiscal_documents')->delete();
        DB::table('contacts')->delete();
    }

    private function seedContacts(): void
    {
        Contact::insert([
            [
                'name' => 'Studio Alfa S.r.l.',
                'vat_number' => 'IT11122233344',
                'tax_code' => '11122233344',
                'email' => 'amministrazione@studioalfa.it',
                'address' => 'Via Dante 3',
                'city' => 'Milano',
                'postal_code' => '20100',
                'province' => 'MI',
                'country' => 'IT',
                'country_code' => 'IT',
                'is_customer' => true,
                'is_supplier' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Beta Consulting SNC',
                'vat_number' => 'IT55566677788',
                'tax_code' => '55566677788',
                'email' => 'contabilita@betaconsulting.it',
                'address' => 'Via Marconi 12',
                'city' => 'Bologna',
                'postal_code' => '40100',
                'province' => 'BO',
                'country' => 'IT',
                'country_code' => 'IT',
                'is_customer' => true,
                'is_supplier' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gamma Digital GmbH',
                'vat_number' => 'DE123456789',
                'tax_code' => null,
                'email' => 'finance@gammadigital.de',
                'address' => 'Alexanderplatz 7',
                'city' => 'Berlin',
                'postal_code' => '10178',
                'province' => null,
                'country' => 'DE',
                'country_code' => 'DE',
                'is_customer' => true,
                'is_supplier' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Forniture Europa S.p.A.',
                'vat_number' => 'IT99887766554',
                'tax_code' => '99887766554',
                'email' => 'amministrazione@fornitureeuropa.it',
                'address' => 'Via del Lavoro 45',
                'city' => 'Torino',
                'postal_code' => '10100',
                'province' => 'TO',
                'country' => 'IT',
                'country_code' => 'IT',
                'is_customer' => false,
                'is_supplier' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedSalesInvoicesFor2025And2026(): void
    {
        $sequence = $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}');
        $contacts = Contact::query()->where('is_customer', true)->orderBy('id')->get()->values();
        $yearCounters = [];

        $dates = [
            Carbon::create(2025, 1, 16),
            Carbon::create(2025, 3, 11),
            Carbon::create(2025, 6, 20),
            Carbon::create(2025, 9, 9),
            Carbon::create(2025, 12, 5),
            Carbon::create(2026, 1, 14),
            Carbon::create(2026, 2, 27),
            Carbon::create(2026, 4, 7),
            now()->subDays(2)->startOfDay(),
        ];

        foreach ($dates as $index => $date) {
            $year = (int) $date->year;
            $nextNumber = ($yearCounters[$year] ?? 0) + 1;
            $yearCounters[$year] = $nextNumber;
            $contact = $contacts[$index % $contacts->count()];
            $base = 90000 + ($index * 4500);
            $pattern = $sequence->pattern ?: '{SEQ}';
            $formattedNumber = str_replace(['{SEQ}', '{ANNO}'], [(string) $nextNumber, (string) $year], $pattern);

            $invoice = FiscalDocument::withoutGlobalScopes()->create([
                'public_id' => (string) Str::ulid(),
                'type' => 'sales',
                'number' => $formattedNumber,
                'sequential_number' => $nextNumber,
                'sequence_id' => $sequence->id,
                'date' => $date->toDateString(),
                'fiscal_year' => $year,
                'contact_id' => $contact->id,
                'status' => 'sent',
                'payment_status' => $index % 3 === 0 ? 'paid' : 'unpaid',
                'due_date' => $date->copy()->addDays(30)->toDateString(),
                'payment_method' => 'MP05',
                'payment_terms' => 'TP02',
                'notes' => 'Prestazione professionale demo',
                'withholding_tax_enabled' => true,
                'withholding_tax_percent' => '20.00',
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            FiscalDocumentLine::create([
                'fiscal_document_id' => $invoice->id,
                'description' => 'Sviluppo e consulenza tecnica',
                'quantity' => 1,
                'unit_price' => $base,
                'vat_rate' => VatRate::R22->value,
                'total' => $base,
            ]);

            FiscalDocumentLine::create([
                'fiscal_document_id' => $invoice->id,
                'description' => 'Supporto operativo',
                'quantity' => 1,
                'unit_price' => 25000,
                'vat_rate' => VatRate::R22->value,
                'total' => 25000,
            ]);

            $invoice->refresh();
            $invoice->calculateTotals();
        }
    }

    private function seedProformaAndConvertedInvoices(): void
    {
        $proformaSequence = $this->resolveSequence('ProForma', 'proforma', 'PRO-{SEQ}');
        $salesSequence = $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}');

        $contact = Contact::query()->where('name', 'Studio Alfa S.r.l.')->firstOrFail();

        $firstProformaDate = Carbon::create(2026, 2, 12);
        $firstProforma = FiscalDocument::withoutGlobalScopes()->create([
            'public_id' => (string) Str::ulid(),
            'type' => 'proforma',
            'number' => 'PRO-1',
            'sequential_number' => 1,
            'sequence_id' => $proformaSequence->id,
            'date' => $firstProformaDate->toDateString(),
            'fiscal_year' => 2026,
            'contact_id' => $contact->id,
            'status' => 'converted',
            'payment_status' => 'paid',
            'due_date' => $firstProformaDate->copy()->addDays(15)->toDateString(),
            'payment_method' => 'MP05',
            'payment_terms' => 'TP02',
            'notes' => 'Proforma per acconto progetto e-commerce',
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => '20.00',
            'created_at' => $firstProformaDate,
            'updated_at' => $firstProformaDate,
        ]);

        FiscalDocumentLine::create([
            'fiscal_document_id' => $firstProforma->id,
            'description' => 'Acconto progetto e-commerce',
            'quantity' => 1,
            'unit_price' => 150000,
            'vat_rate' => VatRate::R22->value,
            'total' => 150000,
        ]);

        $firstProforma->refresh();
        $firstProforma->calculateTotals();

        $firstInvoiceDate = Carbon::create(2026, 2, 20);
        $firstInvoice = FiscalDocument::withoutGlobalScopes()->create([
            'public_id' => (string) Str::ulid(),
            'type' => 'sales',
            'number' => '5',
            'sequential_number' => 5,
            'sequence_id' => $salesSequence->id,
            'date' => $firstInvoiceDate->toDateString(),
            'fiscal_year' => 2026,
            'contact_id' => $contact->id,
            'proforma_id' => $firstProforma->id,
            'status' => 'sent',
            'payment_status' => 'paid',
            'due_date' => $firstInvoiceDate->copy()->addDays(30)->toDateString(),
            'payment_method' => 'MP05',
            'payment_terms' => 'TP02',
            'notes' => 'Saldo progetto e-commerce - conversione da proforma PRO-1',
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => '20.00',
            'created_at' => $firstInvoiceDate,
            'updated_at' => $firstInvoiceDate,
        ]);

        FiscalDocumentLine::create([
            'fiscal_document_id' => $firstInvoice->id,
            'description' => 'Saldo progetto e-commerce',
            'quantity' => 1,
            'unit_price' => 150000,
            'vat_rate' => VatRate::R22->value,
            'total' => 150000,
        ]);

        $firstInvoice->refresh();
        $firstInvoice->calculateTotals();

        $secondProformaDate = Carbon::create(2026, 4, 3);
        $secondProforma = FiscalDocument::withoutGlobalScopes()->create([
            'public_id' => (string) Str::ulid(),
            'type' => 'proforma',
            'number' => 'PRO-2',
            'sequential_number' => 2,
            'sequence_id' => $proformaSequence->id,
            'date' => $secondProformaDate->toDateString(),
            'fiscal_year' => 2026,
            'contact_id' => $contact->id,
            'status' => 'converted',
            'payment_status' => 'unpaid',
            'due_date' => $secondProformaDate->copy()->addDays(15)->toDateString(),
            'payment_method' => 'MP05',
            'payment_terms' => 'TP02',
            'notes' => 'Proforma per manutenzione annuale',
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => '20.00',
            'created_at' => $secondProformaDate,
            'updated_at' => $secondProformaDate,
        ]);

        FiscalDocumentLine::create([
            'fiscal_document_id' => $secondProforma->id,
            'description' => 'Canone manutenzione annuale',
            'quantity' => 1,
            'unit_price' => 120000,
            'vat_rate' => VatRate::R22->value,
            'total' => 120000,
        ]);

        $secondProforma->refresh();
        $secondProforma->calculateTotals();

        $secondInvoiceDate = Carbon::create(2026, 4, 10);
        $secondInvoice = FiscalDocument::withoutGlobalScopes()->create([
            'public_id' => (string) Str::ulid(),
            'type' => 'sales',
            'number' => '8',
            'sequential_number' => 8,
            'sequence_id' => $salesSequence->id,
            'date' => $secondInvoiceDate->toDateString(),
            'fiscal_year' => 2026,
            'contact_id' => $contact->id,
            'proforma_id' => $secondProforma->id,
            'status' => 'sent',
            'payment_status' => 'unpaid',
            'due_date' => $secondInvoiceDate->copy()->addDays(30)->toDateString(),
            'payment_method' => 'MP05',
            'payment_terms' => 'TP02',
            'notes' => 'Fattura emessa da proforma PRO-2',
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => '20.00',
            'created_at' => $secondInvoiceDate,
            'updated_at' => $secondInvoiceDate,
        ]);

        FiscalDocumentLine::create([
            'fiscal_document_id' => $secondInvoice->id,
            'description' => 'Canone manutenzione annuale',
            'quantity' => 1,
            'unit_price' => 120000,
            'vat_rate' => VatRate::R22->value,
            'total' => 120000,
        ]);

        $secondInvoice->refresh();
        $secondInvoice->calculateTotals();
    }

    private function seedSelfInvoices(): void
    {
        $sequence = $this->resolveSequence('Autofatture', 'self_invoice', 'AUTO-{SEQ}');
        $supplier = Contact::query()->where('is_supplier', true)->orderByDesc('id')->firstOrFail();

        $documents = [
            [
                'number' => 'AUTO-1',
                'sequential_number' => 1,
                'date' => Carbon::create(2026, 1, 31),
                'document_type' => 'TD17',
                'related_invoice_number' => 'INV-INT-774',
                'related_invoice_date' => '2026-01-25',
                'description' => 'Servizi marketing da fornitore estero',
                'amount' => 180000,
            ],
            [
                'number' => 'AUTO-2',
                'sequential_number' => 2,
                'date' => Carbon::create(2026, 3, 18),
                'document_type' => 'TD18',
                'related_invoice_number' => 'UE-2026-219',
                'related_invoice_date' => '2026-03-10',
                'description' => 'Acquisto software da fornitore UE',
                'amount' => 95000,
            ],
        ];

        foreach ($documents as $item) {
            $doc = FiscalDocument::withoutGlobalScopes()->create([
                'public_id' => (string) Str::ulid(),
                'type' => 'self_invoice',
                'number' => $item['number'],
                'sequential_number' => $item['sequential_number'],
                'sequence_id' => $sequence->id,
                'date' => $item['date']->toDateString(),
                'fiscal_year' => 2026,
                'contact_id' => $supplier->id,
                'document_type' => $item['document_type'],
                'related_invoice_number' => $item['related_invoice_number'],
                'related_invoice_date' => $item['related_invoice_date'],
                'status' => 'sent',
                'payment_status' => 'paid',
                'payment_method' => 'MP05',
                'payment_terms' => 'TP02',
                'notes' => 'Autofattura demo in reverse charge',
                'withholding_tax_enabled' => false,
                'created_at' => $item['date'],
                'updated_at' => $item['date'],
            ]);

            FiscalDocumentLine::create([
                'fiscal_document_id' => $doc->id,
                'description' => $item['description'],
                'quantity' => 1,
                'unit_price' => $item['amount'],
                'vat_rate' => VatRate::R22->value,
                'total' => $item['amount'],
            ]);

            $doc->refresh();
            $doc->calculateTotals();
        }
    }

    private function seedCreditNotes(): void
    {
        $sequence = $this->resolveSequence('Note di Credito', 'credit_note', 'NC-{SEQ}');
        $contact = Contact::query()->where('is_customer', true)->orderBy('id')->firstOrFail();

        $documents = [
            [
                'number' => 'NC-1',
                'sequential_number' => 1,
                'date' => Carbon::create(2026, 3, 2),
                'description' => 'Storno parziale per attività non erogata',
                'amount' => 30000,
            ],
            [
                'number' => 'NC-2',
                'sequential_number' => 2,
                'date' => Carbon::create(2026, 5, 6),
                'description' => 'Rettifica importo su consulenza strategica',
                'amount' => 18000,
            ],
        ];

        foreach ($documents as $item) {
            $note = FiscalDocument::withoutGlobalScopes()->create([
                'public_id' => (string) Str::ulid(),
                'type' => 'credit_note',
                'document_type' => 'TD04',
                'number' => $item['number'],
                'sequential_number' => $item['sequential_number'],
                'sequence_id' => $sequence->id,
                'date' => $item['date']->toDateString(),
                'fiscal_year' => 2026,
                'contact_id' => $contact->id,
                'status' => 'sent',
                'payment_status' => 'paid',
                'payment_method' => 'MP05',
                'payment_terms' => 'TP02',
                'notes' => 'Nota di credito demo',
                'withholding_tax_enabled' => true,
                'withholding_tax_percent' => '20.00',
                'created_at' => $item['date'],
                'updated_at' => $item['date'],
            ]);

            FiscalDocumentLine::create([
                'fiscal_document_id' => $note->id,
                'description' => $item['description'],
                'quantity' => 1,
                'unit_price' => $item['amount'],
                'vat_rate' => VatRate::R22->value,
                'total' => $item['amount'],
            ]);

            $note->refresh();
            $note->calculateTotals();
        }
    }

    private function resolveSequence(string $name, string $type, string $pattern): Sequence
    {
        return Sequence::firstOrCreate(
            [
                'name' => $name,
                'type' => $type,
            ],
            [
                'pattern' => $pattern,
                'is_system' => true,
            ]
        );
    }
}
