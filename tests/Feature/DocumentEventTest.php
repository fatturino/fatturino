<?php

use App\Models\Contact;
use App\Models\DocumentEvent;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Services\DocumentEventRecorder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

test('document events table and relation are available', function () {
    expect(Schema::hasTable('document_events'))->toBeTrue();

    $invoice = FiscalDocument::factory()->create();
    $event = DocumentEvent::create([
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'created',
        'channel' => 'app',
        'status' => 'success',
        'title' => 'Documento creato',
        'occurred_at' => now(),
    ]);

    expect($invoice->events()->first()->id)->toBe($event->id);
    expect($event->fiscalDocument->id)->toBe($invoice->id);
});

test('document event recorder stores email metadata without body snapshot', function () {
    $invoice = FiscalDocument::factory()->create();

    app(DocumentEventRecorder::class)->emailQueued(
        $invoice,
        'cliente@example.com',
        'Fattura 1',
        'contabilita@example.com',
    );

    $event = DocumentEvent::first();

    expect($event->fiscal_document_id)->toBe($invoice->id);
    expect($event->event_type)->toBe('email_queued');
    expect($event->recipient_email)->toBe('cliente@example.com');
    expect($event->cc)->toBe('contabilita@example.com');
    expect($event->subject)->toBe('Fattura 1');
    expect($event->getAttributes())->not->toHaveKey('body');
});

test('document event recorder stores technical references', function () {
    $invoice = FiscalDocument::factory()->create();

    app(DocumentEventRecorder::class)->record($invoice, [
        'event_type' => 'sdi_result_received',
        'channel' => 'sdi',
        'status' => 'received',
        'title' => 'Esito SDI ricevuto',
        'technical_reference_type' => 'ei_outbound_log',
        'technical_reference_id' => 123,
    ]);

    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sdi_result_received',
        'technical_reference_type' => 'ei_outbound_log',
        'technical_reference_id' => 123,
    ]);
});

test('sales invoice index exposes latest email event for synthetic status', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Cliente Test']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'number' => 'FT-001',
        'date' => now(),
    ]);

    app(DocumentEventRecorder::class)->emailSent($invoice, 'cliente@example.com', 'Fattura FT-001');

    $this->actingAs($user)
        ->get('/sell-invoices')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoices.data.0.latest_email_event.event_type', 'email_sent')
            ->where('invoices.data.0.latest_email_event.recipient_email', 'cliente@example.com')
        );
});
