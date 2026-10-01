<?php

namespace App\Mail;

use App\Models\CreditNote;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use App\Services\CourtesyPdfService;
use App\Services\DocumentStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentMail extends Mailable
{
    public function __construct(
        public readonly string $emailSubject,
        public readonly string $emailBody,
        public readonly ?Model $document = null,
        public readonly string $emailCc = '',
        public readonly string $emailBcc = '',
        public readonly ?string $senderAddress = null,
        public readonly ?string $senderName = null,
        public readonly ?string $replyToAddress = null,
        public readonly ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = $this->configuredSender();
        $replyToAddress = $this->configuredReplyTo();

        return new Envelope(
            from: $fromAddress,
            subject: $this->emailSubject,
            cc: $this->emailCc !== '' ? [new Address($this->emailCc)] : [],
            bcc: $this->emailBcc !== '' ? [new Address($this->emailBcc)] : [],
            replyTo: $replyToAddress !== null ? [$replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.document');
    }

    public function attachments(): array
    {
        if ($this->document === null) {
            return [];
        }

        $pdfService = app(CourtesyPdfService::class);
        $documentStorage = app(DocumentStorageService::class);

        [$data, $filename, $category] = match (true) {
            $this->document instanceof SalesInvoice => [null, $pdfService->generateFileName($this->document), 'sales'],
            $this->document instanceof ProformaInvoice => [null, $pdfService->generateProformaFileName($this->document), 'proforma'],
            $this->document instanceof CreditNote => [null, 'nota-credito-'.$this->document->number.'.pdf', 'credit-notes'],
            default => [null, null, null],
        };

        if ($filename === null || $category === null) {
            return [];
        }

        $data = $this->document->pdf_path
            ? $documentStorage->getPdf($this->document->pdf_path)
            : null;

        if ($data === null) {
            $data = match (true) {
                $this->document instanceof SalesInvoice => $pdfService->generate($this->document)->output(),
                $this->document instanceof ProformaInvoice => $pdfService->generateForProforma($this->document)->output(),
                $this->document instanceof CreditNote => $pdfService->generateForCreditNote($this->document)->output(),
            };
            $path = $documentStorage->storePdf($data, $category, $this->document->date->year, $this->document->public_id, $filename);
            $this->document->update(['pdf_path' => $path]);
        }

        return [
            Attachment::fromData(fn () => $data, $filename)->withMime('application/pdf'),
        ];
    }

    private function configuredSender(): ?Address
    {
        if ($this->senderAddress === null || $this->senderAddress === '') {
            return null;
        }

        return new Address($this->senderAddress, $this->senderName ?: null);
    }

    private function configuredReplyTo(): ?Address
    {
        if ($this->replyToAddress === null || $this->replyToAddress === '') {
            return null;
        }

        return new Address($this->replyToAddress, $this->replyToName ?: null);
    }
}
