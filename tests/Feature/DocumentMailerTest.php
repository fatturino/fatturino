<?php

use App\Jobs\SendPaymentReminderJob;
use App\Mail\DocumentMail;
use App\Models\Contact;
use App\Models\DocumentEvent;
use App\Models\FiscalDocument;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Services\DocumentMailer;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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
    $settings->template_payment_reminder_upcoming_subject = 'Promemoria {NUMERO_DOCUMENTO} {DATA_SCADENZA}';
    $settings->template_payment_reminder_upcoming_body = '{CLIENTE} {IMPORTO_RESIDUO} {DATA_SCADENZA}';
    $settings->template_payment_reminder_overdue_subject = 'Sollecito {NUMERO_DOCUMENTO} {DATA_SCADENZA}';
    $settings->template_payment_reminder_overdue_body = '{CLIENTE} {IMPORTO_RESIDUO} {DATA_SCADENZA}';
    $settings->template_payment_reminder_open_subject = 'Sollecito aperto {NUMERO_DOCUMENTO}';
    $settings->template_payment_reminder_open_body = '{CLIENTE} {IMPORTO_RESIDUO}';
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

test('payment reminder templates render due date and remaining balance', function () {
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-REM-001',
        'due_date' => '2026-10-09',
        'total_gross' => 122000,
        'total_paid' => 22000,
    ]);

    $mailer = app(DocumentMailer::class);

    expect($mailer->renderPaymentReminderSubject('upcoming', $invoice))->toBe('Promemoria FT-REM-001 09/10/2026')
        ->and($mailer->renderPaymentReminderBody('overdue', $invoice))->toContain('Mario Rossi')
        ->toContain('€ 1.000,00')
        ->toContain('09/10/2026');
});

test('manual payment reminder records a dedicated sent event', function () {
    Mail::fake();
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create(['contact_id' => $contact->id, 'number' => 'FT-REM-002', 'due_date' => now()->toDateString(), 'total_gross' => 10000]);

    app(DocumentMailer::class)->sendPaymentReminderNow($invoice, 'upcoming', $contact->email, 'Promemoria', 'Corpo');

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->emailSubject === 'Promemoria');
    expect(DocumentEvent::query()->where('fiscal_document_id', $invoice->id)->where('event_type', 'payment_reminder_queued')->count())->toBe(1)
        ->and(DocumentEvent::query()->where('fiscal_document_id', $invoice->id)->where('event_type', 'payment_reminder_sent')->count())->toBe(1);
});

test('sendPaymentReminder dispatches a queued job without sending immediately', function () {
    Queue::fake();
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create(['contact_id' => $contact->id, 'number' => 'FT-REM-003', 'due_date' => now()->toDateString(), 'total_gross' => 10000]);

    app(DocumentMailer::class)->sendPaymentReminder($invoice, 'upcoming', $contact->email, 'Promemoria', 'Corpo', false);

    Queue::assertPushed(SendPaymentReminderJob::class, function (SendPaymentReminderJob $job) use ($contact, $invoice) {
        return $job->recipientEmail === $contact->email
            && $job->subject === 'Promemoria'
            && $job->body === 'Corpo'
            && $job->scenario === 'upcoming'
            && $job->attachPdf === false
            && $job->document->is($invoice);
    });
    expect(DocumentEvent::query()->where('fiscal_document_id', $invoice->id)->where('event_type', 'payment_reminder_queued')->count())->toBe(1)
        ->and(DocumentEvent::query()->where('fiscal_document_id', $invoice->id)->where('event_type', 'payment_reminder_sent')->count())->toBe(0);
});

test('open payment reminder templates render remaining balance without due date', function () {
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-OPEN-001',
        'due_date' => null,
        'total_gross' => 150000,
        'total_paid' => 50000,
    ]);

    $mailer = app(DocumentMailer::class);

    expect($mailer->renderPaymentReminderSubject('open', $invoice))->toBe('Sollecito aperto FT-OPEN-001')
        ->and($mailer->renderPaymentReminderBody('open', $invoice))->toContain('Mario Rossi')
        ->toContain('€ 1.000,00');
});

test('payment reminder preview accepts invoices without due date using open scenario', function () {
    $user = User::factory()->create();
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-OPEN-002',
        'status' => 'sent',
        'due_date' => null,
        'total_gross' => 10000,
        'total_paid' => 0,
    ]);

    $this->actingAs($user)
        ->getJson("/sell-invoices/{$invoice->id}/payment-reminder-preview?scenario=open")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('preview.scenario', 'open')
        ->assertJsonPath('preview.subject', 'Sollecito aperto FT-OPEN-002');
});

test('payment reminder send accepts upcoming invoices beyond seven days', function () {
    Mail::fake();

    $user = User::factory()->create();
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-FAR-001',
        'status' => 'sent',
        'due_date' => now()->addDays(30)->toDateString(),
        'total_gross' => 10000,
        'total_paid' => 0,
    ]);

    $this->actingAs($user)
        ->postJson("/sell-invoices/{$invoice->id}/send-payment-reminder", [
            'scenario' => 'upcoming',
            'recipient_email' => 'mario@example.com',
            'subject' => 'Promemoria lontano',
            'body' => 'Corpo',
            'attach_pdf' => false,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Sollecito accodato correttamente.',
        ]);

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->emailSubject === 'Promemoria lontano');
    expect(DocumentEvent::query()->where('fiscal_document_id', $invoice->id)->where('event_type', 'payment_reminder_queued')->count())->toBe(1)
        ->and(DocumentEvent::query()->where('fiscal_document_id', $invoice->id)->where('event_type', 'payment_reminder_sent')->count())->toBe(1);
});

test('payment reminder send rejects invoices with zero remaining balance', function () {
    $user = User::factory()->create();
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-PAID-001',
        'status' => 'sent',
        'due_date' => null,
        'total_gross' => 10000,
        'total_paid' => 10000,
    ]);

    $this->actingAs($user)
        ->getJson("/sell-invoices/{$invoice->id}/payment-reminder-preview?scenario=open")
        ->assertStatus(422);
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

test('sendWithOverrides with CC and BCC includes both addresses in envelope', function () {
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
        bcc: 'direzione@example.com',
    );

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasCc('contabilita@example.com')
        && $mail->hasBcc('direzione@example.com'));
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
        'bcc' => 'direzione@example.com',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->document === null);

    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'email_queued',
        'recipient_email' => 'mario@example.com',
        'bcc' => 'direzione@example.com',
        'subject' => 'Oggetto',
    ]);
    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'email_sent',
        'recipient_email' => 'mario@example.com',
        'bcc' => 'direzione@example.com',
        'subject' => 'Oggetto',
    ]);
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
        'direzione@example.com',
    );

    $saved = $invoice->fresh();

    expect($saved->metadata['email']['sent'] ?? null)->toBeTrue();
    expect($saved->metadata['email']['recipient'] ?? null)->toBe('mario@example.com');
    expect($saved->metadata['email']['cc'] ?? null)->toBe('contabilita@example.com');
    expect($saved->metadata['email']['bcc'] ?? null)->toBe('direzione@example.com');
    expect($saved->metadata['email']['sent_at'] ?? null)->not->toBeNull();

    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'email_sent',
        'recipient_email' => 'mario@example.com',
        'cc' => 'contabilita@example.com',
        'bcc' => 'direzione@example.com',
        'subject' => 'Oggetto',
    ]);
});

test('sendNowWithOverrides stores failed event when delivery fails', function () {
    $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'metadata' => [],
    ]);

    Mail::shouldReceive('purge')->twice();
    Mail::shouldReceive('to')
        ->once()
        ->with('mario@example.com')
        ->andThrow(new RuntimeException('SMTP down'));

    expect(fn () => app(DocumentMailer::class)->sendNowWithOverrides(
        $invoice,
        'mario@example.com',
        'Oggetto',
        'Corpo',
    ))->toThrow(RuntimeException::class, 'SMTP down');

    expect(DocumentEvent::where('event_type', 'email_queued')->count())->toBe(1);
    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'email_failed',
        'recipient_email' => 'mario@example.com',
        'subject' => 'Oggetto',
        'error_message' => 'SMTP down',
    ]);

    $saved = $invoice->fresh();
    expect($saved->metadata['email']['status'] ?? null)->toBe('failed');
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
        'direzione@example.com',
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
            && $request['bcc'][0]['email'] === 'direzione@example.com'
            && str_contains($replyToHeader['value'] ?? '', 'fatture@example.com')
            && $request['subject'] === 'Oggetto';
    });
});

test('document mail html includes fatturino footer without company logo', function () {
    $company = app(CompanySettings::class);
    $company->company_logo_path = null;

    $html = (new DocumentMail('Oggetto test', "Ciao cliente,\n\ncorpo messaggio."))->render();

    expect($html)
        ->toContain('Ciao cliente')
        ->toContain('Inviato con')
        ->toContain('Fatturino')
        ->toContain('https://fatturino.it')
        ->not->toContain('<img');
});

test('document mail html embeds company logo when present', function () {
    Storage::fake('logos');

    $logoPath = 'logos/company-logo.png';
    Storage::disk('logos')->put(
        $logoPath,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
    );

    $company = app(CompanySettings::class);
    $company->company_name = 'Azienda Logo';
    $company->company_logo_path = $logoPath;

    $html = (new DocumentMail('Oggetto test', 'Corpo con logo'))->render();

    expect($html)
        ->toContain('Corpo con logo')
        ->toContain('<img')
        ->toContain('data:image/')
        ->toContain('alt="Azienda Logo"')
        ->toContain('Inviato con')
        ->toContain('Fatturino')
        ->toContain('https://fatturino.it');
});

test('document mail embeds legacy public logo when logos disk is empty', function () {
    Storage::fake('logos');
    Storage::fake('public');

    $logoPath = 'logos/legacy-logo.png';
    Storage::disk('public')->put(
        $logoPath,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
    );

    $company = app(CompanySettings::class);
    $company->company_name = 'Legacy Logo';
    $company->company_logo_path = $logoPath;

    $html = (new DocumentMail('Oggetto test', 'Corpo legacy'))->render();

    expect($html)
        ->toContain('<img')
        ->toContain('data:image/')
        ->toContain('alt="Legacy Logo"');
});
