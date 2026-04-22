<?php

use App\Enums\SdiStatus;
use App\Models\Invoice;
use App\Models\SdiLog;

test('can create an SdiLog with valid data', function () {
    $invoice = Invoice::factory()->create();

    $log = SdiLog::create([
        'invoice_id' => $invoice->id,
        'event_type' => 'sent',
        'status' => SdiStatus::Sent,
        'raw_payload' => ['type' => 'customer-invoice'],
        'message' => null,
    ]);

    expect($log->exists)->toBeTrue();
    expect($log->invoice_id)->toBe($invoice->id);
});

test('SdiLog belongs to an Invoice', function () {
    $invoice = Invoice::factory()->create();
    $log = SdiLog::factory()->create(['invoice_id' => $invoice->id]);

    expect($log->invoice)->toBeInstanceOf(Invoice::class);
    expect($log->invoice->id)->toBe($invoice->id);
});

test('status is cast to SdiStatus enum', function () {
    $log = SdiLog::factory()->create(['status' => SdiStatus::Delivered]);

    expect($log->fresh()->status)->toBe(SdiStatus::Delivered);
    expect($log->fresh()->status)->toBeInstanceOf(SdiStatus::class);
});

test('raw_payload is cast to array', function () {
    $payload = ['notification_type' => 'RC', 'invoice_uuid' => 'abc-123'];
    $log = SdiLog::factory()->create(['raw_payload' => $payload]);

    expect($log->fresh()->raw_payload)->toBe($payload);
    expect($log->fresh()->raw_payload)->toBeArray();
});
