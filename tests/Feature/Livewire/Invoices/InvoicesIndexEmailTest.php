<?php

namespace Tests\Feature\Livewire\Invoices;

use App\Livewire\Invoices\Index;
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

class InvoicesIndexEmailTest extends TestCase
{
    use RefreshDatabase;

    private function seedEmailSettings(): void
    {
        $settings = app(EmailSettings::class);
        $settings->template_sales_subject = 'Fattura n. {NUMERO_DOCUMENTO}';
        $settings->template_sales_body = 'Gentile {CLIENTE}.';
        $settings->template_proforma_subject = 'Preventivo n. {NUMERO_DOCUMENTO}';
        $settings->template_proforma_body = 'Gentile {CLIENTE}.';
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

    public function test_send_email_from_index_queues_mail_to_contact()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();

        $contact = Contact::create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
        $sequence = Sequence::factory()->create();
        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('sendEmail', $invoice);

        Mail::assertQueued(DocumentMail::class, fn ($mail) => $mail->hasTo('mario@example.com'));
    }

    public function test_send_email_from_index_shows_error_when_contact_has_no_email()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();

        $contact = Contact::create(['name' => 'Senza Email']); // no email
        $sequence = Sequence::factory()->create();
        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('sendEmail', $invoice);

        Mail::assertNothingQueued();
    }
}
