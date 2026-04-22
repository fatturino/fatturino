<?php

use App\Enums\SdiStatus;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;

test('invoice with null sdi_status is editable', function () {
    $invoice = Invoice::factory()->create(['sdi_status' => null]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('invoice with Sent sdi_status is not editable', function () {
    $invoice = Invoice::factory()->create(['sdi_status' => SdiStatus::Sent]);

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('invoice with Delivered sdi_status is not editable', function () {
    $invoice = Invoice::factory()->create(['sdi_status' => SdiStatus::Delivered]);

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('invoice with Accepted sdi_status is not editable', function () {
    $invoice = Invoice::factory()->create(['sdi_status' => SdiStatus::Accepted]);

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('invoice with Rejected sdi_status is editable', function () {
    $invoice = Invoice::factory()->create(['sdi_status' => SdiStatus::Rejected]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('invoice with Error sdi_status is editable', function () {
    $invoice = Invoice::factory()->create(['sdi_status' => SdiStatus::Error]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('purchase invoice with Delivered sdi_status is not editable', function () {
    $invoice = PurchaseInvoice::factory()->fromSdi('delivered')->create();

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('self invoice with null sdi_status is editable', function () {
    $invoice = SelfInvoice::factory()->create(['sdi_status' => null]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('self invoice with Rejected sdi_status is editable', function () {
    $invoice = SelfInvoice::factory()->create(['sdi_status' => SdiStatus::Rejected]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('self invoice with Sent sdi_status is not editable', function () {
    $invoice = SelfInvoice::factory()->create(['sdi_status' => SdiStatus::Sent]);

    expect($invoice->isSdiEditable())->toBeFalse();
});
