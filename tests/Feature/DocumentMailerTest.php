<?php

use App\Mail\DocumentMail;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Services\DocumentMailer;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Set minimal company settings for placeholder rendering
    $company = app(CompanySettings::class);
    $company->company_name = 'Azienda Test';
    $company->company_vat_number = '12345678901';

    // Set default email templates
    $settings = app(EmailSettings::class);
    $settings->mail_provider = 'smtp';
    $settings->smtp_host = null;
    $settings->smtp_port = null;
    $settings->smtp_username = null;
    $settings->smtp_password = null;
    $settings->smtp_encryption = null;
    $settings->scaleway_tem_region = 'fr-par';
    $settings->scaleway_tem_project_id = null;
    $settings->scaleway_tem_secret_key = null;
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
    $invoice = FiscalDocument::factory()->create([
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
    $invoice = FiscalDocument::factory()->create([
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
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-001',
        'date' => now(),
        'total_gross' => 10000,
    ]);

    app(DocumentMailer::class)->send($invoice, 'mario@example.com');

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('mario@example.com'));
});

test('sendWithOverrides dispatches DocumentMail with custom subject and body', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
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

    Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) {
        return $mail->hasTo('mario@example.com')
            && $mail->emailSubject === 'Oggetto personalizzato'
            && $mail->emailBody === 'Corpo personalizzato';
    });
});

test('sendWithOverrides without PDF does not attach document', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create(['contact_id' => $contact->id]);

    app(DocumentMailer::class)->sendWithOverrides(
        $invoice,
        'mario@example.com',
        'Oggetto',
        'Corpo',
        attachPdf: false,
    );

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->document === null);
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
    $invoice = FiscalDocument::factory()->create([
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
    expect($body)->toContain('12345678901');
    expect($body)->toContain('anna@example.com');
});

test('renderBody replaces outstanding invoices placeholder for the same contact', function () {
    $contact = Contact::create(['name' => 'Anna Verdi', 'email' => 'anna@example.com']);
    $otherContact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);

    FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-100',
        'date' => '2026-06-01',
        'due_date' => '2026-06-30',
        'payment_status' => 'unpaid',
        'total_gross' => 122000,
        'total_paid' => 22000,
    ]);

    FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-101',
        'payment_status' => 'paid',
        'total_gross' => 50000,
        'total_paid' => 50000,
    ]);

    FiscalDocument::factory()->create([
        'contact_id' => $otherContact->id,
        'number' => 'FT-102',
        'payment_status' => 'unpaid',
        'total_gross' => 70000,
    ]);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-103',
        'payment_status' => 'unpaid',
        'total_gross' => 80000,
    ]);

    $settings = app(EmailSettings::class);
    $settings->template_sales_body = "Situazione aperta:\n{FATTURE_NON_SALDATE}";

    $body = app(DocumentMailer::class)->renderBody('sales', $invoice);

    expect($body)->toContain('Ad oggi risultano non saldate le seguenti fatture:');
    expect($body)->toContain('Fattura FT-100');
    expect($body)->toContain('scadenza 30/06/2026');
    expect($body)->toContain('residuo € 1.000,00');
    expect($body)->not->toContain('FT-101');
    expect($body)->not->toContain('FT-102');
    expect($body)->not->toContain('FT-103');
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
    $invoice = FiscalDocument::factory()->create(['contact_id' => $contact->id]);

    app(DocumentMailer::class)->sendWithOverrides(
        $invoice,
        'mario@example.com',
        'Oggetto',
        'Corpo',
        attachPdf: true,
        cc: 'contabilita@example.com',
    );

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasCc('contabilita@example.com'));
});

test('document mail keeps technical sender address and uses settings for name and reply-to', function () {
    $envelope = (new DocumentMail(
        'Oggetto',
        'Corpo',
        senderAddress: 'verified@fatturino.test',
        senderName: 'Studio Rossi',
        replyToAddress: 'fatture@example.com',
        replyToName: 'Studio Rossi',
    ))->envelope();

    expect($envelope->from->address)->toBe('verified@fatturino.test');
    expect($envelope->from->name)->toBe('Studio Rossi');
    expect($envelope->replyTo[0]->address)->toBe('fatture@example.com');
    expect($envelope->replyTo[0]->name)->toBe('Studio Rossi');
});

test('send email endpoint can skip PDF attachment', function () {
    Mail::fake();

    $user = User::factory()->create();
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create(['contact_id' => $contact->id]);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-email", [
        'recipient_email' => 'mario@example.com',
        'subject' => 'Oggetto',
        'body' => 'Corpo',
        'attach_pdf' => false,
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->document === null);
});

test('testConnection returns error string when no from address configured', function () {
    // Ensure both DB setting and env fallback are empty
    $settings = app(EmailSettings::class);
    $settings->from_address = null;
    config(['mail.from.address' => null]);

    $result = app(DocumentMailer::class)->testConnection();

    expect($result)->toBeString()->not->toBeEmpty();
});

test('deliver stores email delivery metadata after successful send', function () {
    Mail::fake();

    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'metadata' => [],
    ]);

    app(DocumentMailer::class)->deliver(
        'mario@example.com',
        'Oggetto',
        'Corpo',
        $invoice,
        true,
        'contabilita@example.com',
    );

    $saved = $invoice->fresh();

    expect($saved->metadata['email']['sent'] ?? null)->toBeTrue();
    expect($saved->metadata['email']['recipient'] ?? null)->toBe('mario@example.com');
    expect($saved->metadata['email']['cc'] ?? null)->toBe('contabilita@example.com');
    expect($saved->metadata['email']['sent_at'] ?? null)->not->toBeNull();
});

test('deliver sends through Scaleway TEM when selected', function () {
    Http::fake([
        'https://api.scaleway.com/transactional-email/v1alpha1/regions/fr-par/emails' => Http::response(['id' => 'email-123'], 200),
    ]);
    config(['mail.from.address' => 'verified@fatturino.test']);

    $settings = app(EmailSettings::class);
    $settings->mail_provider = 'scaleway_tem';
    $settings->from_address = 'fatture@example.com';
    $settings->from_name = 'Fatturino';
    $settings->scaleway_tem_region = 'fr-par';
    $settings->scaleway_tem_project_id = 'project-123';
    $settings->scaleway_tem_secret_key = 'secret-123';

    app(DocumentMailer::class)->deliver(
        'mario@example.com',
        'Oggetto',
        'Corpo',
        null,
        false,
        'contabilita@example.com',
    );

    Http::assertSent(function ($request) {
        $replyToHeader = collect($request['additional_headers'] ?? [])
            ->first(fn (array $header): bool => ($header['key'] ?? '') === 'Reply-To');

        return $request->hasHeader('X-Auth-Token', 'secret-123')
            && $request->url() === 'https://api.scaleway.com/transactional-email/v1alpha1/regions/fr-par/emails'
            && $request['project_id'] === 'project-123'
            && $request['from']['email'] === 'verified@fatturino.test'
            && $request['from']['name'] === 'Fatturino'
            && $request['to'][0]['email'] === 'mario@example.com'
            && $request['cc'][0]['email'] === 'contabilita@example.com'
            && str_contains($replyToHeader['value'] ?? '', 'fatture@example.com')
            && $request['subject'] === 'Oggetto';
    });
});
