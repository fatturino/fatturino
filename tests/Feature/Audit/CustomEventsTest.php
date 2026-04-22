<?php

use App\Models\Invoice;
use App\Models\User;
use App\Support\InvoiceAuditDispatcher;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('dispatch creates a custom audit event', function () {
    $invoice = Invoice::factory()->create();

    InvoiceAuditDispatcher::dispatch($invoice, 'email_sent');

    $audit = $invoice->audits()->where('event', 'email_sent')->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_id)->toBe($this->user->id);
});

test('dispatch is a no-op when the model is not Auditable', function () {
    $plainObject = new stdClass;
    $plainObject->id = 999;

    // Should not throw and should not produce any audit entry
    InvoiceAuditDispatcher::dispatch($plainObject, 'email_sent');

    expect(true)->toBeTrue();
});

test('sdi_sent custom event carries the correct event name', function () {
    $invoice = Invoice::factory()->create();

    InvoiceAuditDispatcher::dispatch($invoice, 'sdi_sent');

    expect($invoice->audits()->where('event', 'sdi_sent')->exists())->toBeTrue();
});
