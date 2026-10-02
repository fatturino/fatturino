<?php

namespace App\Services;

use App\Enums\SdiStatus;
use App\Models\DocumentEvent;
use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LogicException;

class InAppNotificationDispatcher
{
    public function __construct(
        private readonly InAppNotificationPublisher $publisher,
        private readonly NotificationRecipientResolver $recipientResolver,
    ) {}

    public function sdiOutcomeReceived(Model $document, EiOutboundLog $outboundLog, SdiStatus $status, string $message): void
    {
        [$severity, $title] = match ($status) {
            SdiStatus::Delivered, SdiStatus::Accepted => ['success', 'Fattura consegnata allo SDI'],
            SdiStatus::NotDelivered, SdiStatus::Expired => ['warning', 'Fattura non consegnata'],
            SdiStatus::Rejected, SdiStatus::Refused => ['error', 'Fattura scartata dallo SDI'],
            default => ['info', 'Esito SDI ricevuto'],
        };

        $this->publish(
            type: 'sdi.outcome.received',
            category: 'sdi',
            severity: $severity,
            title: $title,
            body: $message,
            sourceType: 'ei_outbound_log',
            sourceId: (string) $outboundLog->id,
            dedupeKey: "sdi-outcome:{$document->getKey()}:{$outboundLog->event_type}:{$status->value}",
            document: $document,
        );
    }

    public function purchaseInvoiceReceived(Model $document, EiInboundLog $inboundLog): void
    {
        $number = $document->getAttribute('number') ?: '#'.$document->getKey();

        $this->publish(
            type: 'sdi.purchase_invoice.received',
            category: 'sdi',
            severity: 'info',
            title: 'Nuova fattura di acquisto',
            body: "Ricevuta la fattura {$number}.",
            sourceType: 'ei_inbound_log',
            sourceId: (string) $inboundLog->id,
            dedupeKey: "purchase-invoice-received:{$inboundLog->id}",
            document: $document,
        );
    }

    public function documentEmailSent(Model $document, DocumentEvent $event): void
    {
        $number = $document->getAttribute('number') ?: '#'.$document->getKey();

        $this->publish(
            type: 'email.document.sent',
            category: 'email',
            severity: 'success',
            title: 'Email inviata',
            body: "Il documento {$number} è stato inviato a {$event->recipient_email}.",
            sourceType: 'document_event',
            sourceId: (string) $event->id,
            dedupeKey: "email-sent:{$event->id}",
            document: $document,
        );
    }

    private function publish(
        string $type,
        string $category,
        string $severity,
        string $title,
        string $body,
        string $sourceType,
        string $sourceId,
        string $dedupeKey,
        Model $document,
    ): void {
        try {
            $this->publisher->publish(new InAppNotificationMessage(
                recipientId: $this->recipientResolver->resolve()->id,
                type: $type,
                category: $category,
                severity: $severity,
                title: $title,
                body: $body,
                sourceType: $sourceType,
                sourceId: $sourceId,
                dedupeKey: $dedupeKey,
                occurredAt: now(),
                resourceType: 'fiscal_document',
                resourceId: $document->getKey(),
                action: 'open_document',
            ));
        } catch (LogicException $exception) {
            Log::warning('In-app notification skipped because no recipient is available.', [
                'type' => $type,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);
        }
    }
}
