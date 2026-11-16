<?php

use App\Enums\InvoiceStatus;
use App\Jobs\SendDocumentToSdiJob;
use App\Mcp\Servers\FatturinoServer;
use App\Mcp\Tools\CreateSelfInvoiceTool;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\InvoiceSettings;
use Illuminate\Support\Facades\Queue;

it('creates a draft self invoice via mcp without sending to sdi', function () {
    Queue::fake();

    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Fornitore MCP']);
    $sequence = Sequence::factory()->selfInvoice()->create();
    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_self_invoice = $sequence->id;
    $settings->save();

    FatturinoServer::actingAs($user)->tool(CreateSelfInvoiceTool::class, [
        'contact_id' => $contact->id,
        'date' => now()->toDateString(),
        'document_type' => 'TD17',
        'related_invoice_number' => 'FOR-MCP-1',
        'related_invoice_date' => now()->subDay()->toDateString(),
        'notes' => 'Creata via MCP',
        'lines' => [[
            'description' => 'Servizio',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 'R22',
        ]],
    ])
        ->assertOk()
        ->assertStructuredContentPaths([
            'status' => 'draft',
            'type' => 'self_invoice',
            'document_type' => 'TD17',
            'sdi_sent' => false,
            'contact_id' => $contact->id,
        ]);

    $invoice = SelfInvoice::query()->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->related_invoice_number)->toBe('FOR-MCP-1');

    Queue::assertNothingPushed();
    Queue::assertNotPushed(SendDocumentToSdiJob::class);
});

it('returns a tool error for invalid create_self_invoice payloads', function () {
    $user = User::factory()->create();
    Sequence::factory()->selfInvoice()->create();

    FatturinoServer::actingAs($user)->tool(CreateSelfInvoiceTool::class, [
        'contact_id' => 999999,
        'date' => now()->toDateString(),
        'document_type' => 'TD17',
        'related_invoice_number' => 'FOR-X',
        'related_invoice_date' => now()->toDateString(),
        'lines' => [[
            'description' => 'Servizio',
            'quantity' => 1,
            'unit_price' => 10,
            'vat_rate' => 'R22',
        ]],
    ])->assertHasErrors();

    expect(SelfInvoice::query()->count())->toBe(0);
});
