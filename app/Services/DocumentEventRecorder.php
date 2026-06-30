<?php

namespace App\Services;

use App\Models\DocumentEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DocumentEventRecorder
{
    public const EMAIL_EVENT_TYPES = [
        'email_queued',
        'email_sent',
        'email_failed',
        'payment_reminder_queued',
        'payment_reminder_sent',
        'payment_reminder_failed',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(Model $document, array $attributes): ?DocumentEvent
    {
        if (! $document->exists) {
            return null;
        }

        return DocumentEvent::create([
            'fiscal_document_id' => $document->getKey(),
            'event_type' => $attributes['event_type'],
            'channel' => $attributes['channel'] ?? null,
            'status' => $attributes['status'] ?? null,
            'title' => $attributes['title'],
            'message' => $attributes['message'] ?? null,
            'recipient_email' => $attributes['recipient_email'] ?? null,
            'cc' => $attributes['cc'] ?? null,
            'subject' => $attributes['subject'] ?? null,
            'error_message' => $attributes['error_message'] ?? null,
            'technical_reference_type' => $attributes['technical_reference_type'] ?? null,
            'technical_reference_id' => $attributes['technical_reference_id'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'created_by' => $attributes['created_by'] ?? Auth::id(),
        ]);
    }

    public function created(Model $document): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'created',
            'channel' => 'app',
            'status' => 'success',
            'title' => 'Documento creato',
            'message' => $this->documentLabel($document).' creato.',
        ]);
    }

    public function xmlValidated(Model $document, bool $success, ?string $message = null): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'xml_validated',
            'channel' => 'xml',
            'status' => $success ? 'success' : 'failed',
            'title' => $success ? 'XML validato' : 'Validazione XML fallita',
            'message' => $message,
        ]);
    }

    public function sdiSent(Model $document, ?int $outboundLogId = null, ?string $message = null): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'sdi_sent',
            'channel' => 'sdi',
            'status' => 'success',
            'title' => 'Documento inviato allo SDI',
            'message' => $message,
            'technical_reference_type' => $outboundLogId ? 'ei_outbound_log' : null,
            'technical_reference_id' => $outboundLogId,
        ]);
    }

    public function sdiResultReceived(Model $document, ?int $outboundLogId = null, ?string $message = null): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'sdi_result_received',
            'channel' => 'sdi',
            'status' => 'received',
            'title' => 'Esito SDI ricevuto',
            'message' => $message,
            'technical_reference_type' => $outboundLogId ? 'ei_outbound_log' : null,
            'technical_reference_id' => $outboundLogId,
        ]);
    }

    public function emailQueued(Model $document, string $recipientEmail, string $subject, string $cc = ''): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'email_queued',
            'channel' => 'email',
            'status' => 'queued',
            'title' => 'Email accodata',
            'recipient_email' => $recipientEmail,
            'cc' => $cc,
            'subject' => $subject,
        ]);
    }

    public function emailSent(Model $document, string $recipientEmail, string $subject, string $cc = ''): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'email_sent',
            'channel' => 'email',
            'status' => 'success',
            'title' => 'Email inviata',
            'recipient_email' => $recipientEmail,
            'cc' => $cc,
            'subject' => $subject,
        ]);
    }

    public function emailFailed(Model $document, string $recipientEmail, string $subject, string $errorMessage, string $cc = ''): ?DocumentEvent
    {
        return $this->record($document, [
            'event_type' => 'email_failed',
            'channel' => 'email',
            'status' => 'failed',
            'title' => 'Invio email fallito',
            'recipient_email' => $recipientEmail,
            'cc' => $cc,
            'subject' => $subject,
            'error_message' => $errorMessage,
        ]);
    }

    private function documentLabel(Model $document): string
    {
        $number = $document->getAttribute('number');

        return $number ? 'Documento '.$number : 'Documento #'.$document->getKey();
    }
}
