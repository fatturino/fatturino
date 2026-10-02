<?php

namespace Database\Seeders;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\DocumentEvent;
use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\InAppNotification;
use App\Models\Payment;
use App\Models\SdiOutboundSubmission;
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
    /** @var array<string, FiscalDocument> */
    private array $documents = [];

    /** @var array<string, int> */
    private array $sdiLogIds = [];

    /** @var array<string, int> */
    private array $eventIds = [];

    private int $inboundLogId;

    private User $demoUser;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedDemoUser();
            $this->seedCompanyAndInvoiceSettings();
            $this->resetOperationalData();
            $this->seedContacts();
            $this->seedHistoricalSalesInvoices();
            $this->seedCurrentSalesInvoices();
            $this->seedPurchaseInvoices();
            $this->seedProformas();
            $this->seedSelfInvoices();
            $this->seedCreditNotes();
            $this->seedPayments();
            $this->seedDocumentEventsAndSdiLogs();
            $this->seedNotifications();
        });
    }

    private function seedDemoUser(): void
    {
        $this->demoUser = User::updateOrCreate(
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

        $invoice = app(InvoiceSettings::class);
        $invoice->default_sequence_sales = $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}')->id;
        $invoice->default_sequence_proforma = $this->resolveSequence('ProForma', 'proforma', 'PRO-{SEQ}')->id;
        $invoice->default_sequence_self_invoice = $this->resolveSequence('Autofatture', 'self_invoice', 'AUTO-{SEQ}')->id;
        $invoice->default_sequence_credit_notes = $this->resolveSequence('Note di Credito', 'credit_note', 'NC-{SEQ}')->id;
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
    }

    private function seedContacts(): void
    {
        $now = now();

        Contact::insert([
            $this->contact('Studio Alfa S.r.l.', 'IT11122233344', '11122233344', 'amministrazione@studioalfa.it', 'Via Dante 3', 'Milano', '20100', 'MI', true, false, $now, 'ALFA001'),
            $this->contact('Beta Consulting SNC', 'IT55566677788', '55566677788', 'contabilita@betaconsulting.it', 'Via Marconi 12', 'Bologna', '40100', 'BO', true, true, $now, 'BETAC01'),
            $this->contact('Tecnologie Verdi S.p.A.', 'IT12345678901', '12345678901', 'amministrazione@tecnologieverdi.it', 'Via dell\'Innovazione 18', 'Padova', '35100', 'PD', true, false, $now, 'TVSP001'),
            $this->contact('Arco Design S.r.l.', 'IT98765432109', '98765432109', 'fatture@arcodesign.it', 'Corso Francia 120', 'Torino', '10143', 'TO', true, false, $now, 'ARCO001'),
            $this->contact('Gamma Digital GmbH', 'DE123456789', null, 'finance@gammadigital.de', 'Alexanderplatz 7', 'Berlin', '10178', null, true, true, $now, null, 'DE'),
            $this->contact('Forniture Europa S.p.A.', 'IT99887766554', '99887766554', 'amministrazione@fornitureeuropa.it', 'Via del Lavoro 45', 'Torino', '10100', 'TO', false, true, $now, 'FORNEU1'),
            $this->contact('Cloud Services Ireland Ltd', 'IE6388047V', null, 'billing@cloudservices.example', 'Grand Canal Dock 4', 'Dublin', 'D02', null, false, true, $now, null, 'IE'),
            $this->contact('Office Market S.r.l.', 'IT22446688002', '22446688002', 'ordini@officemarket.it', 'Via Roma 91', 'Monza', '20900', 'MB', false, true, $now, 'OFFICE1'),
        ]);
    }

    private function seedHistoricalSalesInvoices(): void
    {
        $customers = ['Studio Alfa S.r.l.', 'Beta Consulting SNC', 'Tecnologie Verdi S.p.A.', 'Arco Design S.r.l.'];
        $amounts = [98000, 145000, 212000, 126000, 178000, 235000, 164000, 192000];

        foreach ([now()->year - 2, now()->year - 1] as $yearOffset => $year) {
            foreach ($amounts as $index => $amount) {
                $date = Carbon::create($year, ($index % 8) + 1, min(24, 4 + ($index * 3)));
                $document = $this->createDocument("sales-history-{$year}-{$index}", [
                    'type' => 'sales',
                    'number' => (string) ($index + 1),
                    'sequential_number' => $index + 1,
                    'sequence_id' => $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}')->id,
                    'date' => $date,
                    'contact' => $customers[$index % count($customers)],
                    'status' => 'sent',
                    'due_date' => $date->copy()->addDays(30),
                    'notes' => 'Prestazione professionale conclusa.',
                    'withholding_tax_enabled' => true,
                    'withholding_tax_percent' => '20.00',
                    'lines' => [['description' => 'Consulenza e sviluppo software', 'amount' => $amount]],
                ]);

                $this->addPayment($document, $document->net_due, $date->copy()->addDays(25), 'Saldo fattura storica');
            }
        }
    }

    private function seedCurrentSalesInvoices(): void
    {
        $today = now()->startOfDay();
        $sequence = $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}');

        $this->createDocument('sales-paid-recent', [
            'type' => 'sales', 'number' => '1', 'sequential_number' => 1, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(23), 'contact' => 'Studio Alfa S.r.l.', 'status' => 'sent',
            'due_date' => $today->copy()->subDays(3), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Analisi infrastruttura cloud', 'amount' => 185000]],
        ]);
        $this->createDocument('sales-overdue', [
            'type' => 'sales', 'number' => '2', 'sequential_number' => 2, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(50), 'contact' => 'Beta Consulting SNC', 'status' => 'sent',
            'due_date' => $today->copy()->subDays(20), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'notes' => 'Da sollecitare: secondo promemoria già inviato.',
            'lines' => [['description' => 'Sviluppo portale clienti', 'amount' => 320000]],
        ]);
        $this->createDocument('sales-partial-overdue', [
            'type' => 'sales', 'number' => '3', 'sequential_number' => 3, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(42), 'contact' => 'Tecnologie Verdi S.p.A.', 'status' => 'sent',
            'due_date' => $today->copy()->subDays(12), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Integrazione gestionale ERP', 'amount' => 450000]],
        ]);
        $this->createDocument('sales-due-soon', [
            'type' => 'sales', 'number' => '4', 'sequential_number' => 4, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(14), 'contact' => 'Arco Design S.r.l.', 'status' => 'sent',
            'due_date' => $today->copy()->addDays(3), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Formazione team operativo', 'amount' => 125000]],
        ]);
        $this->createDocument('sales-future-due', [
            'type' => 'sales', 'number' => '5', 'sequential_number' => 5, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(5), 'contact' => 'Studio Alfa S.r.l.', 'status' => 'sent',
            'due_date' => $today->copy()->addDays(18), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Canone assistenza trimestrale', 'amount' => 78000]],
        ]);
        $this->createDocument('sales-ready-for-sdi', [
            'type' => 'sales', 'number' => '6', 'sequential_number' => 6, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(1), 'contact' => 'Beta Consulting SNC', 'status' => 'xml_validated',
            'due_date' => $today->copy()->addDays(30), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Consulenza strategica mensile', 'amount' => 140000]],
        ]);
        $this->createDocument('sales-draft', [
            'type' => 'sales', 'number' => '7', 'sequential_number' => 7, 'sequence_id' => $sequence->id,
            'date' => $today, 'contact' => 'Tecnologie Verdi S.p.A.', 'status' => 'draft',
            'due_date' => $today->copy()->addDays(30), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Proposta evolutiva piattaforma', 'amount' => 210000]],
        ]);
    }

    private function seedPurchaseInvoices(): void
    {
        $today = now()->startOfDay();
        $documents = [
            ['key' => 'purchase-paid-history', 'number' => 'FE-2025-884', 'date' => Carbon::create(now()->year - 1, 10, 12), 'contact' => 'Forniture Europa S.p.A.', 'amount' => 86000, 'status' => 'sent', 'source' => 'manual'],
            ['key' => 'purchase-sdi-received', 'number' => 'CS-'.now()->year.'-091', 'date' => $today->copy()->subDays(18), 'contact' => 'Cloud Services Ireland Ltd', 'amount' => 134000, 'status' => 'sent', 'source' => 'sdi_sync'],
            ['key' => 'purchase-overdue', 'number' => 'OM-'.now()->year.'-442', 'date' => $today->copy()->subDays(45), 'contact' => 'Office Market S.r.l.', 'amount' => 62000, 'status' => 'sent', 'source' => 'manual'],
            ['key' => 'purchase-partial', 'number' => 'FE-'.now()->year.'-125', 'date' => $today->copy()->subDays(27), 'contact' => 'Forniture Europa S.p.A.', 'amount' => 176000, 'status' => 'sent', 'source' => 'manual'],
        ];

        foreach ($documents as $index => $item) {
            $document = $this->createDocument($item['key'], [
                'type' => 'purchase', 'number' => $item['number'], 'date' => $item['date'], 'contact' => $item['contact'],
                'status' => $item['status'], 'source' => $item['source'], 'due_date' => $item['date']->copy()->addDays($index === 2 ? 15 : 30),
                'withholding_tax_enabled' => false, 'lines' => [['description' => $index === 1 ? 'Servizi cloud e hosting' : 'Acquisto beni e servizi', 'amount' => $item['amount']]],
            ]);
            if ($index === 0) {
                $this->addPayment($document, $document->net_due, $item['date']->copy()->addDays(20), 'Pagamento fornitore storico');
            }
        }
    }

    private function seedProformas(): void
    {
        $today = now()->startOfDay();
        $sequence = $this->resolveSequence('ProForma', 'proforma', 'PRO-{SEQ}');
        $converted = $this->createDocument('proforma-converted', [
            'type' => 'proforma', 'number' => 'PRO-1', 'sequential_number' => 1, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(60), 'contact' => 'Studio Alfa S.r.l.', 'status' => 'converted',
            'due_date' => $today->copy()->subDays(30), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Acconto progetto e-commerce', 'amount' => 150000]],
        ]);
        $invoice = $this->createDocument('sales-from-proforma', [
            'type' => 'sales', 'number' => '8', 'sequential_number' => 8, 'sequence_id' => $this->resolveSequence('Fatture Elettroniche', 'sales', '{SEQ}')->id,
            'date' => $today->copy()->subDays(29), 'contact' => 'Studio Alfa S.r.l.', 'status' => 'sent', 'proforma_id' => $converted->id,
            'due_date' => $today->copy()->addDays(1), 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
            'lines' => [['description' => 'Acconto progetto e-commerce', 'amount' => 150000]],
        ]);
        $this->documents['sales-from-proforma'] = $invoice;

        $this->createDocument('proforma-draft', [
            'type' => 'proforma', 'number' => 'PRO-2', 'sequential_number' => 2, 'sequence_id' => $sequence->id,
            'date' => $today, 'contact' => 'Arco Design S.r.l.', 'status' => 'draft', 'due_date' => $today->copy()->addDays(15),
            'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00', 'lines' => [['description' => 'Workshop di discovery', 'amount' => 90000]],
        ]);
        $this->createDocument('proforma-sent', [
            'type' => 'proforma', 'number' => 'PRO-3', 'sequential_number' => 3, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(2), 'contact' => 'Beta Consulting SNC', 'status' => 'sent', 'due_date' => $today->copy()->addDays(13),
            'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00', 'lines' => [['description' => 'Canone manutenzione annuale', 'amount' => 120000]],
        ]);
        $this->createDocument('proforma-settled', [
            'type' => 'proforma', 'number' => 'PRO-4', 'sequential_number' => 4, 'sequence_id' => $sequence->id,
            'date' => $today->copy()->subDays(20), 'contact' => 'Tecnologie Verdi S.p.A.', 'status' => 'sent', 'due_date' => $today->copy()->subDays(5),
            'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00', 'lines' => [['description' => 'Analisi di fattibilità', 'amount' => 110000]],
        ]);
    }

    private function seedSelfInvoices(): void
    {
        $sequence = $this->resolveSequence('Autofatture', 'self_invoice', 'AUTO-{SEQ}');
        foreach ([
            ['number' => 'AUTO-1', 'date' => now()->startOfDay()->subDays(35), 'type' => 'TD17', 'contact' => 'Cloud Services Ireland Ltd', 'related' => 'CS-2026-078', 'amount' => 134000],
            ['number' => 'AUTO-2', 'date' => now()->startOfDay()->subDays(12), 'type' => 'TD18', 'contact' => 'Gamma Digital GmbH', 'related' => 'GD-'.now()->year.'-219', 'amount' => 95000],
            ['number' => 'AUTO-3', 'date' => now()->startOfDay()->subDays(3), 'type' => 'TD19', 'contact' => 'Forniture Europa S.p.A.', 'related' => 'FE-'.now()->year.'-125', 'amount' => 71000],
        ] as $index => $item) {
            $this->createDocument('self-invoice-'.($index + 1), [
                'type' => 'self_invoice', 'number' => $item['number'], 'sequential_number' => $index + 1, 'sequence_id' => $sequence->id,
                'date' => $item['date'], 'contact' => $item['contact'], 'status' => 'sent', 'document_type' => $item['type'],
                'related_invoice_number' => $item['related'], 'related_invoice_date' => $item['date']->copy()->subDays(4),
                'due_date' => $item['date']->copy()->addDays(30), 'withholding_tax_enabled' => false,
                'lines' => [['description' => 'Autofattura reverse charge', 'amount' => $item['amount']]],
            ]);
        }
    }

    private function seedCreditNotes(): void
    {
        $sequence = $this->resolveSequence('Note di Credito', 'credit_note', 'NC-{SEQ}');
        foreach ([
            ['date' => now()->startOfDay()->subDays(70), 'contact' => 'Studio Alfa S.r.l.', 'amount' => 30000, 'description' => 'Storno parziale per attività non erogata'],
            ['date' => now()->startOfDay()->subDays(9), 'contact' => 'Beta Consulting SNC', 'amount' => 18000, 'description' => 'Rettifica importo su consulenza strategica'],
        ] as $index => $item) {
            $this->createDocument('credit-note-'.($index + 1), [
                'type' => 'credit_note', 'document_type' => 'TD04', 'number' => 'NC-'.($index + 1), 'sequential_number' => $index + 1,
                'sequence_id' => $sequence->id, 'date' => $item['date'], 'contact' => $item['contact'], 'status' => 'sent',
                'payment_status' => 'paid', 'withholding_tax_enabled' => true, 'withholding_tax_percent' => '20.00',
                'lines' => [['description' => $item['description'], 'amount' => $item['amount']]],
            ]);
        }
    }

    private function seedPayments(): void
    {
        $today = now()->startOfDay();
        $this->addPayment($this->documents['sales-paid-recent'], $this->documents['sales-paid-recent']->net_due, $today->copy()->subDays(2), 'Bonifico ricevuto');
        $this->addPayment($this->documents['sales-partial-overdue'], 180000, $today->copy()->subDays(25), 'Acconto cliente');
        $this->addPayment($this->documents['purchase-partial'], 100000, $today->copy()->subDays(5), 'Bonifico parziale a fornitore');
        $this->addPayment($this->documents['proforma-converted'], $this->documents['proforma-converted']->net_due, $today->copy()->subDays(32), 'Incasso acconto');
        $this->addPayment($this->documents['sales-from-proforma'], $this->documents['sales-from-proforma']->net_due, $today->copy()->subDays(1), 'Saldo conversione proforma');
        $this->addPayment($this->documents['proforma-settled'], $this->documents['proforma-settled']->net_due, $today->copy()->subDays(8), 'Incasso da fatturare');

        foreach ($this->documents as $document) {
            $document->recalculatePaymentStatus();
        }
    }

    private function seedDocumentEventsAndSdiLogs(): void
    {
        $today = now()->startOfDay();
        $this->seedSdiOutcome('sales-paid-recent', 'delivered', 'Consegnata dal Sistema di Interscambio.', $today->copy()->subDays(20));
        $this->seedSdiOutcome('sales-overdue', 'delivered', 'Consegnata dal Sistema di Interscambio.', $today->copy()->subDays(48));
        $this->seedSdiOutcome('sales-partial-overdue', 'not_delivered', 'Mancata consegna: il documento è disponibile nel cassetto fiscale.', $today->copy()->subDays(40));
        $this->seedSdiOutcome('sales-future-due', 'rejected', 'Scarto SDI: codice destinatario non valido.', $today->copy()->subDays(4));

        $inbound = EiInboundLog::create([
            'event_name' => 'supplier_invoice_received', 'event_fingerprint' => 'demo-purchase-inbound-'.now()->year,
            'source_uuid' => 'demo-inbound-'.now()->year, 'notification_type' => 'supplier_invoice', 'processing_status' => 'processed',
            'attempts' => 1, 'processed_at' => $today->copy()->subDays(17), 'linked_fiscal_document_id' => $this->documents['purchase-sdi-received']->id,
            'raw_payload' => ['invoice_number' => $this->documents['purchase-sdi-received']->number],
        ]);
        $this->inboundLogId = $inbound->id;
        $this->documents['purchase-sdi-received']->forceFill(['sdi_status' => 'received', 'sdi_received_at' => $today->copy()->subDays(17), 'sdi_processed' => true])->save();

        $this->eventIds['sales-overdue'] = $this->createEvent($this->documents['sales-overdue'], 'payment_reminder_sent', 'email', 'sent', 'Sollecito di pagamento inviato', 'Promemoria di pagamento inviato al cliente.', $today->copy()->subDays(4))->id;
        $this->eventIds['sales-paid-recent'] = $this->createEvent($this->documents['sales-paid-recent'], 'email_sent', 'email', 'sent', 'Email inviata', 'Documento inviato via email al cliente.', $today->copy()->subDays(22))->id;
        $this->eventIds['proforma-sent'] = $this->createEvent($this->documents['proforma-sent'], 'email_sent', 'email', 'sent', 'Proforma inviata', 'Proforma inviata al cliente per approvazione.', $today->copy()->subDays(2))->id;

        $this->documents['purchase-sdi-received']->setRelation('inboundLog', $inbound);
    }

    private function seedNotifications(): void
    {
        $today = now()->startOfDay();
        $notifications = [
            ['document' => 'sales-paid-recent', 'type' => 'sdi.outcome.received', 'category' => 'sdi', 'severity' => 'success', 'title' => 'Fattura consegnata allo SDI', 'body' => 'La fattura '.$this->documents['sales-paid-recent']->number.' è stata consegnata.', 'source_type' => 'ei_outbound_log', 'source_id' => $this->sdiLogIds['sales-paid-recent'], 'read_at' => $today->copy()->subDays(18)],
            ['document' => 'sales-partial-overdue', 'type' => 'sdi.outcome.received', 'category' => 'sdi', 'severity' => 'warning', 'title' => 'Fattura non consegnata', 'body' => 'La fattura è disponibile nel cassetto fiscale.', 'source_type' => 'ei_outbound_log', 'source_id' => $this->sdiLogIds['sales-partial-overdue'], 'read_at' => null],
            ['document' => 'sales-future-due', 'type' => 'sdi.outcome.received', 'category' => 'sdi', 'severity' => 'error', 'title' => 'Fattura scartata dallo SDI', 'body' => 'Verifica il codice destinatario e invia nuovamente il documento.', 'source_type' => 'ei_outbound_log', 'source_id' => $this->sdiLogIds['sales-future-due'], 'read_at' => null],
            ['document' => 'purchase-sdi-received', 'type' => 'sdi.purchase_invoice.received', 'category' => 'sdi', 'severity' => 'info', 'title' => 'Nuova fattura di acquisto', 'body' => 'Ricevuta la fattura '.$this->documents['purchase-sdi-received']->number.'.', 'source_type' => 'ei_inbound_log', 'source_id' => $this->inboundLogId, 'read_at' => null],
            ['document' => 'sales-overdue', 'type' => 'email.document.sent', 'category' => 'email', 'severity' => 'success', 'title' => 'Sollecito di pagamento inviato', 'body' => 'Il promemoria è stato inviato al cliente.', 'source_type' => 'document_event', 'source_id' => $this->eventIds['sales-overdue'], 'read_at' => $today->copy()->subDays(3)],
            ['document' => 'proforma-sent', 'type' => 'email.document.sent', 'category' => 'email', 'severity' => 'success', 'title' => 'Email inviata', 'body' => 'La proforma è stata inviata al cliente.', 'source_type' => 'document_event', 'source_id' => $this->eventIds['proforma-sent'], 'read_at' => null],
        ];

        foreach ($notifications as $index => $notification) {
            $document = $this->documents[$notification['document']];
            InAppNotification::create([
                'user_id' => $this->demoUser->id, 'type' => $notification['type'], 'category' => $notification['category'], 'severity' => $notification['severity'],
                'title' => $notification['title'], 'body' => $notification['body'], 'resource_type' => 'fiscal_document', 'resource_id' => $document->id,
                'action' => 'open_document', 'source_type' => $notification['source_type'], 'source_id' => $notification['source_id'],
                'dedupe_key' => 'demo-notification-'.($index + 1), 'metadata' => ['document_number' => $document->number],
                'occurred_at' => $today->copy()->subDays(6 - $index), 'read_at' => $notification['read_at'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function createDocument(string $key, array $data): FiscalDocument
    {
        /** @var Carbon $date */
        $date = $data['date'];
        $contact = Contact::query()->where('name', $data['contact'])->firstOrFail();
        $document = FiscalDocument::withoutGlobalScopes()->create([
            'public_id' => (string) Str::ulid(), 'type' => $data['type'], 'document_type' => $data['document_type'] ?? null,
            'number' => $data['number'], 'sequential_number' => $data['sequential_number'] ?? null, 'sequence_id' => $data['sequence_id'] ?? null,
            'date' => $date->toDateString(), 'fiscal_year' => $date->year, 'contact_id' => $contact->id, 'proforma_id' => $data['proforma_id'] ?? null,
            'related_invoice_number' => $data['related_invoice_number'] ?? null, 'related_invoice_date' => isset($data['related_invoice_date']) ? $data['related_invoice_date']->toDateString() : null,
            'status' => $data['status'], 'payment_status' => $data['payment_status'] ?? 'unpaid', 'due_date' => isset($data['due_date']) ? $data['due_date']->toDateString() : null,
            'payment_method' => 'MP05', 'payment_terms' => 'TP02', 'notes' => $data['notes'] ?? null, 'source' => $data['source'] ?? 'manual',
            'withholding_tax_enabled' => $data['withholding_tax_enabled'] ?? false, 'withholding_tax_percent' => $data['withholding_tax_percent'] ?? null,
            'created_at' => $date, 'updated_at' => $date,
        ]);
        foreach ($data['lines'] as $line) {
            FiscalDocumentLine::create(['fiscal_document_id' => $document->id, 'description' => $line['description'], 'quantity' => 1, 'unit_price' => $line['amount'], 'vat_rate' => VatRate::R22->value, 'total' => $line['amount']]);
        }
        $document->calculateTotals();
        $document->refresh();
        $this->documents[$key] = $document;

        return $document;
    }

    private function addPayment(FiscalDocument $document, int $amount, Carbon $paidAt, string $reference): void
    {
        Payment::create(['fiscal_document_id' => $document->id, 'amount' => $amount, 'paid_at' => $paidAt->toDateString(), 'payment_method' => 'MP05', 'reference' => $reference]);
    }

    private function seedSdiOutcome(string $documentKey, string $status, string $message, Carbon $occurredAt): void
    {
        $document = $this->documents[$documentKey];
        $log = EiOutboundLog::create(['fiscal_document_id' => $document->id, 'source_uuid' => 'demo-'.$documentKey, 'event_type' => 'outcome', 'status' => $status, 'message' => $message, 'raw_payload' => ['status' => $status]]);
        $document->forceFill(['sdi_status' => $status, 'sdi_message' => $message, 'sdi_sent_at' => $occurredAt])->save();
        $this->sdiLogIds[$documentKey] = $log->id;
        SdiOutboundSubmission::create(['fiscal_document_id' => $document->id, 'idempotency_key' => 'demo-'.$documentKey, 'provider' => 'openapi', 'status' => $status === 'rejected' ? 'provider_rejected' : 'completed', 'xml_sha256' => hash('sha256', $documentKey), 'business_fingerprint' => hash('sha256', 'demo-business'), 'provider_uuid' => (string) $log->id, 'provider_accepted_at' => $occurredAt, 'completed_at' => $occurredAt, 'reconciled_at' => $occurredAt]);
    }

    private function createEvent(FiscalDocument $document, string $type, string $channel, string $status, string $title, string $message, Carbon $occurredAt): DocumentEvent
    {
        return DocumentEvent::create(['fiscal_document_id' => $document->id, 'event_type' => $type, 'channel' => $channel, 'status' => $status, 'title' => $title, 'message' => $message, 'recipient_email' => $document->contact->email, 'occurred_at' => $occurredAt, 'created_by' => $this->demoUser->id]);
    }

    /** @return array<string, mixed> */
    private function contact(string $name, ?string $vatNumber, ?string $taxCode, string $email, string $address, string $city, string $postalCode, ?string $province, bool $customer, bool $supplier, Carbon $now, ?string $sdiCode = null, string $country = 'IT'): array
    {
        return ['name' => $name, 'vat_number' => $vatNumber, 'tax_code' => $taxCode, 'email' => $email, 'address' => $address, 'city' => $city, 'postal_code' => $postalCode, 'province' => $province, 'country' => $country, 'country_code' => $country, 'sdi_code' => $sdiCode, 'is_customer' => $customer, 'is_supplier' => $supplier, 'created_at' => $now, 'updated_at' => $now];
    }

    private function resolveSequence(string $name, string $type, string $pattern): Sequence
    {
        return Sequence::firstOrCreate(['name' => $name, 'type' => $type], ['pattern' => $pattern, 'is_system' => true]);
    }
}
