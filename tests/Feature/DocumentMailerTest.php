<?php

use App\Mail\DocumentMail;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use App\Services\DocumentMailer;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Set minimal company settings for placeholder rendering
    $company = app(CompanySettings::class);
    $company->company_name = 'Azienda Test';
    $company->company_vat_number = 'IT12345678901';

    // Set default email templates
    $settings = app(EmailSettings::class);
    $settings->smtp_host = null;
    $settings->smtp_port = null;
    $settings->smtp_username = null;
    $settings->smtp_password = null;
    $settings->smtp_encryption = null;
    $settings->from_address = null;
    $settings->from_name = null;
    $settings->template_sales_subject = 'Fattura n. {NUMERO_DOCUMENTO} del {DATA_DOCUMENTO}';
    $settings->template_sales_body = "Gentile {CLIENTE},\n\nFattura n. {NUMERO_DOCUMENTO} - Totale: {IMPORTO_TOTALE}.\n\nCordiali saluti,\n{AZIENDA}";
    $settings->template_proforma_subject = 'Preventivo n. {NUMERO_DOCUMENTO}';
    $settings->template_proforma_body = 'Gentile {CLIENTE}, preventivo n. {NUMERO_DOCUMENTO}.';
    $settings->auto_send_sales = false;
    $settings->auto_send_proforma = false;
});

test('renderSubject replaces placeholders correctly', function () {
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = Invoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-001',
        'date' => '2026-03-15',
        'total_net' => 100000,
        'total_vat' => 22000,
        'total_gross' => 122000,
    ]);

    $mailer = app(DocumentMailer::class);
    $subject = $mailer->renderSubject('sales', $invoice);

    expect($subject)->toBe('Fattura n. FT-001 del 15/03/2026');
});

test('renderBody replaces all placeholders correctly', function () {
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = Invoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-001',
        'date' => '2026-03-15',
        'total_net' => 100000,
        'total_vat' => 22000,
        'total_gross' => 122000,
    ]);

    $mailer = app(DocumentMailer::class);
    $body = $mailer->renderBody('sales', $invoice);

    expect($body)->toContain('Mario Rossi');
    expect($body)->toContain('FT-001');
    expect($body)->toContain('1.220,00');
    expect($body)->toContain('Azienda Test');
});

test('send dispatches DocumentMail with default template', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = Invoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-001',
        'date' => now(),
        'total_gross' => 10000,
    ]);

    app(DocumentMailer::class)->send($invoice, 'mario@example.com');

    Mail::assertQueued(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('mario@example.com'));
});

test('sendWithOverrides dispatches DocumentMail with custom subject and body', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = Invoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-001',
        'date' => now(),
        'total_gross' => 10000,
    ]);

    app(DocumentMailer::class)->sendWithOverrides(
        $invoice,
        'mario@example.com',
        'Oggetto personalizzato',
        'Corpo personalizzato',
    );

    Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail) {
        return $mail->hasTo('mario@example.com')
            && $mail->emailSubject === 'Oggetto personalizzato'
            && $mail->emailBody === 'Corpo personalizzato';
    });
});

test('sendWithOverrides without PDF does not attach document', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

    app(DocumentMailer::class)->sendWithOverrides(
        $invoice,
        'mario@example.com',
        'Oggetto',
        'Corpo',
        attachPdf: false,
    );

    Mail::assertQueued(DocumentMail::class, fn (DocumentMail $mail) => $mail->document === null);
});

test('renderSubject for proforma replaces placeholders correctly', function () {
    $contact = Contact::create(['name' => 'Luigi Bianchi', 'email' => 'luigi@example.com']);
    $proforma = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'PRO-042',
        'date' => '2026-01-10',
    ]);

    $subject = app(DocumentMailer::class)->renderSubject('proforma', $proforma);

    expect($subject)->toBe('Preventivo n. PRO-042');
});

test('renderBody for proforma replaces all placeholders correctly', function () {
    $contact = Contact::create(['name' => 'Luigi Bianchi', 'email' => 'luigi@example.com']);
    $proforma = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'PRO-042',
        'date' => '2026-01-10',
    ]);

    $body = app(DocumentMailer::class)->renderBody('proforma', $proforma);

    expect($body)->toContain('Luigi Bianchi');
    expect($body)->toContain('PRO-042');
});

test('renderBody replaces all monetary and company placeholders', function () {
    $contact = Contact::create([
        'name' => 'Anna Verdi',
        'email' => 'anna@example.com',
    ]);
    $invoice = Invoice::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-099',
        'date' => '2026-06-01',
        'total_net' => 100000,
        'total_vat' => 22000,
        'total_gross' => 122000,
    ]);

    // Template that exercises every supported placeholder
    $settings = app(EmailSettings::class);
    $settings->template_sales_body = '{CLIENTE} {NUMERO_DOCUMENTO} {DATA_DOCUMENTO} {IMPORTO_NETTO} {IMPORTO_IVA} {IMPORTO_TOTALE} {AZIENDA} {PARTITA_IVA_AZIENDA} {EMAIL_CLIENTE}';

    $body = app(DocumentMailer::class)->renderBody('sales', $invoice);

    expect($body)->toContain('Anna Verdi');
    expect($body)->toContain('FT-099');
    expect($body)->toContain('01/06/2026');
    expect($body)->toContain('€ 1.000,00');  // IMPORTO_NETTO
    expect($body)->toContain('€ 220,00');    // IMPORTO_IVA
    expect($body)->toContain('€ 1.220,00'); // IMPORTO_TOTALE
    expect($body)->toContain('Azienda Test');
    expect($body)->toContain('IT12345678901');
    expect($body)->toContain('anna@example.com');
});

test('testConnection returns null on successful send', function () {
    Mail::fake();

    $settings = app(EmailSettings::class);
    $settings->from_address = 'test@example.com';

    $result = app(DocumentMailer::class)->testConnection();

    expect($result)->toBeNull();
});

test('sendWithOverrides with CC includes CC address in envelope', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

    app(DocumentMailer::class)->sendWithOverrides(
        $invoice,
        'mario@example.com',
        'Oggetto',
        'Corpo',
        attachPdf: true,
        cc: 'contabilita@example.com',
    );

    Mail::assertQueued(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasCc('contabilita@example.com'));
});

test('testConnection returns error string when no from address configured', function () {
    // Ensure both DB setting and env fallback are empty
    $settings = app(EmailSettings::class);
    $settings->from_address = null;
    config(['mail.from.address' => null]);

    $result = app(DocumentMailer::class)->testConnection();

    expect($result)->toBeString()->not->toBeEmpty();
});
