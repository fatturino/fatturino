<?php

namespace App\Mail;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use App\Services\CourtesyPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $emailBody,
        // Stored as a model so SerializesModels handles it safely (no binary in queue payload)
        public readonly ?Model $document = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
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

        try {
            $pdfService = app(CourtesyPdfService::class);

            [$data, $filename] = match (true) {
                $this->document instanceof Invoice        => [$pdfService->generate($this->document)->output(), $pdfService->generateFileName($this->document)],
                $this->document instanceof ProformaInvoice => [$pdfService->generateForProforma($this->document)->output(), $pdfService->generateProformaFileName($this->document)],
                $this->document instanceof CreditNote     => [$pdfService->generateForCreditNote($this->document)->output(), 'nota-credito-'.$this->document->number.'.pdf'],
                default                                   => [null, null],
            };

            if ($data === null) {
                return [];
            }

            return [
                Attachment::fromData(fn () => $data, $filename)->withMime('application/pdf'),
            ];
        } catch (\Throwable) {
            // PDF generation failure must not block email delivery
            return [];
        }
    }
}
