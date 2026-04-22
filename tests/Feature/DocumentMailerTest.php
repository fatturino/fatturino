<?php

use App\Mail\DocumentMail;
use App\Models\Contact;
use App\Models\Invoice;
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
