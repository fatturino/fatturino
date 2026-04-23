<?php

namespace Tests\Feature\Livewire\Proforma;

use App\Livewire\Proforma\Edit;
use App\Mail\DocumentMail;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\EmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ProformaEmailTest extends TestCase
{
    use RefreshDatabase;

    private function createProforma(?Contact $contact = null): ProformaInvoice
    {
        $contact ??= Contact::create(['name' => 'Luigi Bianchi', 'email' => 'luigi@example.com']);
        $sequence = Sequence::factory()->create();

        return ProformaInvoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
            'number' => 'PRO-001',
            'date' => now()->format('Y-m-d'),
            'total_gross' => 5000,
        ]);
    }

    private function seedEmailSettings(): void
    {
        $settings = app(EmailSettings::class);
        $settings->template_sales_subject = 'Fattura n. {NUMERO_DOCUMENTO}';
        $settings->template_sales_body = 'Gentile {CLIENTE}, fattura n. {NUMERO_DOCUMENTO}.';
        $settings->template_proforma_subject = 'Preventivo n. {NUMERO_DOCUMENTO}';
        $settings->template_proforma_body = 'Gentile {CLIENTE}, preventivo n. {NUMERO_DOCUMENTO}.';
        $settings->auto_send_sales = false;
        $settings->auto_send_proforma = false;
        $settings->smtp_host = null;
        $settings->from_address = null;
        $settings->from_name = null;
        $settings->smtp_port = null;
        $settings->smtp_username = null;
        $settings->smtp_password = null;
        $settings->smtp_encryption = null;
    }

    public function test_email_modal_opens_with_prefilled_data()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $proforma = $this->createProforma();

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->call('openEmailModal')
            ->assertSet('emailModal', true)
            ->assertSet('emailRecipient', 'luigi@example.com')
            ->assertSet('emailSubject', 'Preventivo n. PRO-001')
            ->assertSet('emailBody', 'Gentile Luigi Bianchi, preventivo n. PRO-001.');
    }

    public function test_send_email_queues_document_mail()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $proforma = $this->createProforma();

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->set('emailModal', true)
            ->set('emailRecipient', 'luigi@example.com')
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', 'Test Body')
            ->call('sendEmail');

        Mail::assertQueued(DocumentMail::class, fn ($mail) => $mail->hasTo('luigi@example.com'));
    }

    public function test_send_email_closes_modal_and_shows_no_errors()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $proforma = $this->createProforma();

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->set('emailModal', true)
            ->set('emailRecipient', 'luigi@example.com')
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', 'Test Body')
            ->call('sendEmail')
            ->assertSet('emailModal', false)
            ->assertHasNoErrors();

        // ProformaInvoice does not implement Auditable, so no audit row is created.
        // The dispatcher silently skips non-auditable models.
        $this->assertDatabaseMissing('audits', ['event' => 'email_sent']);
    }

    public function test_send_email_validates_recipient_email()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $proforma = $this->createProforma();

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->set('emailRecipient', 'not-an-email')
            ->set('emailSubject', 'Test')
            ->set('emailBody', 'Test')
            ->call('sendEmail')
            ->assertHasErrors(['emailRecipient']);
    }

    public function test_mark_as_sent_triggers_auto_send_when_enabled()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();

        app(EmailSettings::class)->auto_send_proforma = true;

        $proforma = $this->createProforma();

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->call('markAsSent');

        Mail::assertQueued(DocumentMail::class, fn ($mail) => $mail->hasTo('luigi@example.com'));
    }

    public function test_mark_as_sent_skips_auto_send_when_disabled()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();

        // auto_send_proforma is false by default in seedEmailSettings
        $proforma = $this->createProforma();

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->call('markAsSent');

        Mail::assertNothingQueued();
    }

    public function test_auto_send_skips_when_contact_has_no_email()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();

        app(EmailSettings::class)->auto_send_proforma = true;

        $contact = Contact::create(['name' => 'Senza Email']); // no email field
        $proforma = $this->createProforma($contact);

        Livewire::actingAs($user)
            ->test(Edit::class, ['proformaInvoice' => $proforma])
            ->call('markAsSent');

        Mail::assertNothingQueued();
    }
}
