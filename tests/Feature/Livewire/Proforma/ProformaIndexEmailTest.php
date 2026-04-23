<?php

namespace Tests\Feature\Livewire\Proforma;

use App\Livewire\Proforma\Index;
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

class ProformaIndexEmailTest extends TestCase
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

        $contact = Contact::create(['name' => 'Luigi Bianchi', 'email' => 'luigi@example.com']);
        $sequence = Sequence::factory()->create();
        $proforma = ProformaInvoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('sendEmail', $proforma);

        Mail::assertQueued(DocumentMail::class, fn ($mail) => $mail->hasTo('luigi@example.com'));
    }

    public function test_send_email_from_index_shows_error_when_contact_has_no_email()
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->seedEmailSettings();

        $contact = Contact::create(['name' => 'Senza Email']); // no email
        $sequence = Sequence::factory()->create();
        $proforma = ProformaInvoice::factory()->create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('sendEmail', $proforma);

        Mail::assertNothingQueued();
    }
}
