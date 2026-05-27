<?php

use App\Enums\SdiStatus;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;

test('can create an outbound SDI log with valid data', function () {
    $invoice = FiscalDocument::factory()->create();

    $log = EiOutboundLog::create([
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sent',
        'status' => SdiStatus::Sent,
        'raw_payload' => ['type' => 'customer-invoice'],
        'message' => null,
    ]);

    expect($log->exists)->toBeTrue();
    expect($log->fiscal_document_id)->toBe($invoice->id);
});

test('outbound SDI log belongs to a fiscal document', function () {
    $invoice = FiscalDocument::factory()->create();
    $log = EiOutboundLog::factory()->create(['fiscal_document_id' => $invoice->id]);

    expect($log->fiscalDocument)->toBeInstanceOf(FiscalDocument::class);
    expect($log->fiscalDocument->id)->toBe($invoice->id);
});

test('status is cast to SdiStatus enum', function () {
    $log = EiOutboundLog::factory()->create(['status' => SdiStatus::Delivered]);

    expect($log->fresh()->status)->toBe(SdiStatus::Delivered);
    expect($log->fresh()->status)->toBeInstanceOf(SdiStatus::class);
});

test('raw_payload is cast to array', function () {
    $payload = ['notification_type' => 'RC', 'invoice_uuid' => 'abc-123'];
    $log = EiOutboundLog::factory()->create(['raw_payload' => $payload]);

    expect($log->fresh()->raw_payload)->toBe($payload);
    expect($log->fresh()->raw_payload)->toBeArray();
});
