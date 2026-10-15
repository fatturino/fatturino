<?php

namespace Database\Seeders;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\Payment;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Settings\OpenApiSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OpenApiSandboxSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            throw new RuntimeException('The OpenAPI sandbox seeder cannot run in production.');
        }

        DB::transaction(function (): void {
            $this->resetOperationalData();
            $this->seedSandboxUser();
            $this->seedSequencesAndSettings();
            $this->seedContacts();
            $this->seedDocuments();
        });
    }

    private function resetOperationalData(): void
    {
        DB::table('in_app_notifications')->delete();
        DB::table('document_events')->delete();
        DB::table('sdi_outbound_submissions')->delete();
        DB::table('sdi_uuid_links')->delete();
        DB::table('payments')->delete();
        DB::table('ei_outbound_logs')->delete();
        DB::table('ei_inbound_logs')->delete();
        DB::table('fiscal_documents_lines')->delete();
        DB::table('fiscal_documents')->delete();
        DB::table('contacts')->delete();
        DB::table('personal_access_tokens')->delete();
        DB::table('sessions')->delete();
        DB::table('password_reset_tokens')->delete();
        DB::table('users')->delete();
    }

    private function seedSandboxUser(): void
    {
        User::create([
            'name' => 'Amministratore Sandbox',
            'email' => 'sandbox@fatturino.test',
            'email_verified_at' => now(),
            'password' => Hash::make('sandbox'),
            'is_admin' => true,
        ]);
    }

    private function seedSequencesAndSettings(): void
    {
        $this->call(SequenceSeeder::class);

        $salesSequence = Sequence::query()->where('name', 'Fatture Elettroniche')->sole();

        $company = app(CompanySettings::class);
        $company->company_name = 'Fatturino Sandbox RF01';
        $company->company_vat_number = '51080560454';
        $company->company_tax_code = '51080560454';
        $company->company_address = 'Via dei Dati Sintetici 1';
        $company->company_city = 'Milano';
        $company->company_postal_code = '20100';
        $company->company_province = 'MI';
        $company->company_country = 'IT';
        $company->company_email = 'sandbox@example.test';
        $company->company_pec = 'sandbox@pec.example.test';
        $company->company_sdi_code = '0000000';
        $company->company_fiscal_regime = 'RF01';
        $company->save();

        $invoice = app(InvoiceSettings::class);
        $invoice->default_sequence_sales = $salesSequence->id;
        $invoice->default_sequence_purchase = Sequence::query()->where('name', 'Acquisti')->sole()->id;
        $invoice->default_sequence_proforma = Sequence::query()->where('name', 'ProForma')->sole()->id;
        $invoice->default_sequence_self_invoice = Sequence::query()->where('name', 'Autofatture')->sole()->id;
        $invoice->default_sequence_credit_notes = Sequence::query()->where('name', 'Note di Credito')->sole()->id;
        $invoice->default_vat_rate = VatRate::R22;
        $invoice->default_payment_method = 'MP05';
        $invoice->default_payment_terms = 'TP02';
        $invoice->withholding_tax_enabled = false;
        $invoice->yearly_numbering_reset = true;
        $invoice->save();

        $openApi = app(OpenApiSettings::class);
        $openApi->sandbox = true;
        $openApi->company_sdi_code = '0000000';
        $openApi->activated = false;
        $openApi->save();
    }

    private function seedContacts(): void
    {
        $now = now();

        Contact::insert([
            $this->contact('Cliente Sandbox Alfa S.r.l.', 'IT98765432109', '98765432109', 'amministrazione.alfa@example.test', $now),
            $this->contact('Cliente Sandbox Beta S.r.l.', 'IT11122233344', '11122233344', 'amministrazione.beta@example.test', $now),
            $this->contact('Fornitore Sandbox S.r.l.', 'IT55566677788', '55566677788', 'acquisti@example.test', $now, false, true),
            $this->contact('Fornitore UE Sandbox GmbH', 'DE123456789', '', 'eu@example.test', $now, false, true, 'DE'),
        ]);
    }

    private function seedDocuments(): void
    {
        $sequence = Sequence::query()->where('name', 'Fatture Elettroniche')->sole();
        $currentYear = now()->year;

        foreach ([$currentYear - 1, $currentYear] as $year) {
            $invoice = $this->createDocument($sequence, 'sales', $year, 1, 'Cliente Sandbox Alfa S.r.l.', 125000, 'sent');
            Payment::create(['fiscal_document_id' => $invoice->id, 'amount' => $invoice->net_due, 'paid_at' => Carbon::create($year, 4, 10), 'payment_method' => 'MP05', 'reference' => 'Incasso sintetico sandbox']);
            $invoice->recalculatePaymentStatus();
            $this->createDocument($sequence, 'sales', $year, 2, 'Cliente Sandbox Beta S.r.l.', 86000, 'sent');
        }

        $this->createDocument(
            $sequence,
            'sales',
            $currentYear,
            3,
            'Cliente Sandbox Alfa S.r.l.',
            149000,
            'xml_validated'
        );
        $this->createDocument(
            $sequence,
            'sales',
            $currentYear,
            4,
            'Cliente Sandbox Beta S.r.l.',
            62000,
            'draft'
        );

        $this->createDocument(Sequence::query()->where('name', 'Acquisti')->sole(), 'purchase', $currentYear, 1, 'Fornitore Sandbox S.r.l.', 45000, 'sent');
        $this->createDocument(Sequence::query()->where('name', 'ProForma')->sole(), 'proforma', $currentYear, 1, 'Cliente Sandbox Alfa S.r.l.', 72000, 'sent');
        $this->createDocument(Sequence::query()->where('name', 'Autofatture')->sole(), 'self_invoice', $currentYear, 1, 'Fornitore UE Sandbox GmbH', 38000, 'sent', 'TD17');
        $this->createDocument(Sequence::query()->where('name', 'Note di Credito')->sole(), 'credit_note', $currentYear, 1, 'Cliente Sandbox Beta S.r.l.', 12000, 'sent', 'TD04');
    }

    private function createDocument(Sequence $sequence, string $type, int $year, int $number, string $contactName, int $amount, string $status, ?string $documentType = null): FiscalDocument
    {
        $month = $year === now()->year
            ? max(1, now()->month - (4 - $number))
            : ($number === 1 ? 3 : 9);
        $date = Carbon::create($year, $month, 15);
        $contact = Contact::query()->where('name', $contactName)->sole();
        $documentNumber = $type === 'sales'
            ? (string) $number
            : str_replace('{SEQ}', (string) $number, $sequence->pattern);

        $document = FiscalDocument::withoutGlobalScopes()->create([
            'public_id' => (string) Str::ulid(),
            'type' => $type,
            'document_type' => $documentType,
            'number' => $documentNumber,
            'sequential_number' => $number,
            'sequence_id' => $sequence->id,
            'date' => $date->toDateString(),
            'fiscal_year' => $year,
            'contact_id' => $contact->id,
            'status' => $status,
            'payment_status' => 'unpaid',
            'due_date' => $date->copy()->addDays(30)->toDateString(),
            'payment_method' => 'MP05',
            'payment_terms' => 'TP02',
            'notes' => 'Dato sintetico per test OpenAPI sandbox.',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        FiscalDocumentLine::create([
            'fiscal_document_id' => $document->id,
            'description' => 'Servizio sintetico OpenAPI sandbox',
            'quantity' => 1,
            'unit_price' => $amount,
            'vat_rate' => VatRate::R22->value,
            'total' => $amount,
        ]);

        $document->calculateTotals();

        return $document->refresh();
    }

    /** @return array<string, mixed> */
    private function contact(string $name, string $vatNumber, string $taxCode, string $email, Carbon $now, bool $customer = true, bool $supplier = false, string $country = 'IT'): array
    {
        return [
            'name' => $name,
            'vat_number' => $vatNumber,
            'tax_code' => $taxCode,
            'email' => $email,
            'address' => 'Via dei Test 10',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => $country,
            'country_code' => $country,
            'sdi_code' => '0000000',
            'is_customer' => $customer,
            'is_supplier' => $supplier,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
