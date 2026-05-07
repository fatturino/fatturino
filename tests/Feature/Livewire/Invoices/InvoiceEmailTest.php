<?php

namespace Tests\Feature\Livewire\Invoices;

use App\Livewire\Invoices\Edit;
use App\Mail\DocumentMail;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\EmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(): Invoice
    {
        $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
        $sequence = Sequence::factory()->create();

        return Invoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
            'number' => 'FT-001',
            'date' => now()->format('Y-m-d'),
            'total_gross' => 12200,
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
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->call('openEmailModal')
            ->assertSet('emailModal', true)
            ->assertSet('emailRecipient', 'mario@example.com')
            ->assertSet('emailSubject', 'Fattura n. FT-001')
            ->assertSet('emailBody', 'Gentile Mario Rossi, fattura n. FT-001.');
    }

    public function test_send_email_dispatches_email_sent_audit()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailModal', true)
            ->set('emailRecipient', 'mario@example.com')
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', 'Test Body')
            ->call('sendEmail')
            ->assertSet('emailModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'event' => 'email_sent',
            'user_id' => $user->id,
        ]);
    }

    public function test_send_email_validates_recipient_email()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailRecipient', 'not-an-email')
            ->set('emailSubject', 'Test')
            ->set('emailBody', 'Test')
            ->call('sendEmail')
            ->assertHasErrors(['emailRecipient']);
    }

    public function test_email_button_not_shown_when_contact_has_no_email()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();

        $contact = Contact::create(['name' => 'Mario Rossi']); // no email
        $sequence = Sequence::factory()->create();
        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
        ]);

        $this->actingAs($user)
            ->get(route('sell-invoices.edit', $invoice))
            ->assertDontSee('openEmailModal');
    }

    public function test_send_email_queues_document_mail()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailModal', true)
            ->set('emailRecipient', 'mario@example.com')
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', 'Test Body')
            ->call('sendEmail');

        Mail::assertSent(DocumentMail::class, fn ($mail) => $mail->hasTo('mario@example.com'));
    }

    public function test_send_email_validates_empty_subject()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailRecipient', 'mario@example.com')
            ->set('emailSubject', '')
            ->set('emailBody', 'Test Body')
            ->call('sendEmail')
            ->assertHasErrors(['emailSubject']);
    }

    public function test_send_email_validates_empty_body()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailRecipient', 'mario@example.com')
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', '')
            ->call('sendEmail')
            ->assertHasErrors(['emailBody']);
    }

    public function test_send_email_with_cc_queues_mail_with_cc_address()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailModal', true)
            ->set('emailRecipient', 'mario@example.com')
            ->set('emailCc', 'contabilita@example.com')
            ->set('emailSubject', 'Test')
            ->set('emailBody', 'Test')
            ->call('sendEmail');

        Mail::assertSent(DocumentMail::class, fn ($mail) => $mail->hasCc('contabilita@example.com'));
    }

    public function test_send_email_validates_invalid_cc_email()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailRecipient', 'mario@example.com')
            ->set('emailCc', 'not-an-email')
            ->set('emailSubject', 'Test')
            ->set('emailBody', 'Test')
            ->call('sendEmail')
            ->assertHasErrors(['emailCc']);
    }

    public function test_open_email_modal_resets_cc_to_empty()
    {
        $user = User::factory()->create();
        $this->seedEmailSettings();
        $invoice = $this->createInvoice();

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('emailCc', 'old@example.com')
            ->call('openEmailModal')
            ->assertSet('emailCc', '');
    }
}
