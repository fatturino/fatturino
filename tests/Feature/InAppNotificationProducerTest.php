<?php

use App\Enums\SdiStatus;
use App\Models\DocumentEvent;
use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\InAppNotification;
use App\Models\SdiOutboundSubmission;
use App\Models\User;
use App\Services\InAppNotificationDispatcher;

test('the configured producers publish the supported in-app notifications', function () {
    $recipient = User::factory()->create(['is_admin' => true]);
    $invoice = FiscalDocument::factory()->create(['number' => 'FV-2026-001']);
    $outboundLog = EiOutboundLog::query()->create([
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'RC',
        'status' => SdiStatus::Delivered->value,
    ]);
    $inboundLog = EiInboundLog::query()->create([
        'event_name' => 'supplier-invoice',
        'event_fingerprint' => 'supplier:invoice:'.$invoice->id,
    ]);
    $emailEvent = DocumentEvent::query()->create([
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'email_sent',
        'channel' => 'email',
        'status' => 'success',
        'title' => 'Email inviata',
        'recipient_email' => 'cliente@example.test',
        'occurred_at' => now(),
    ]);
    $submission = SdiOutboundSubmission::query()->create([
        'fiscal_document_id' => $invoice->id,
        'idempotency_key' => '01TESTSUBMISSIONKEY000001',
        'provider' => 'openapi_legacy',
        'status' => 'completed',
        'xml_sha256' => hash('sha256', 'xml'),
        'business_fingerprint' => hash('sha256', 'fingerprint'),
        'dispatch_started_at' => now(),
        'provider_accepted_at' => now(),
        'completed_at' => now(),
    ]);

    $dispatcher = app(InAppNotificationDispatcher::class);
    $dispatcher->sdiOutcomeReceived($invoice, $outboundLog, SdiStatus::Delivered, 'Consegnata dal Sistema di Interscambio.');
    $dispatcher->purchaseInvoiceReceived($invoice, $inboundLog);
    $dispatcher->documentEmailSent($invoice, $emailEvent);
    $dispatcher->sdiSendAccepted($invoice, $submission, 'Fattura inviata allo SDI.');
    $dispatcher->sdiSendFailed($invoice, $submission, 'Provider offline', false);

    expect(InAppNotification::query()->whereBelongsTo($recipient)->count())->toBe(5)
        ->and(InAppNotification::query()->where('type', 'sdi.outcome.received')->value('severity'))->toBe('success')
        ->and(InAppNotification::query()->where('type', 'sdi.purchase_invoice.received')->value('source_id'))->toBe((string) $inboundLog->id)
        ->and(InAppNotification::query()->where('type', 'email.document.sent')->value('source_id'))->toBe((string) $emailEvent->id)
        ->and(InAppNotification::query()->where('type', 'sdi.send.accepted')->value('severity'))->toBe('success')
        ->and(InAppNotification::query()->where('type', 'sdi.send.failed')->value('severity'))->toBe('error');
});

test('the SDI producer does not duplicate the same logical outcome', function () {
    User::factory()->create(['is_admin' => true]);
    $invoice = FiscalDocument::factory()->create();
    $outboundLog = EiOutboundLog::query()->create([
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'NS',
        'status' => SdiStatus::Rejected->value,
    ]);

    $dispatcher = app(InAppNotificationDispatcher::class);
    $dispatcher->sdiOutcomeReceived($invoice, $outboundLog, SdiStatus::Rejected, 'Documento scartato.');
    $dispatcher->sdiOutcomeReceived($invoice, $outboundLog, SdiStatus::Rejected, 'Documento scartato.');

    expect(InAppNotification::query()->count())->toBe(1)
        ->and(InAppNotification::query()->first()->severity)->toBe('error');
});
