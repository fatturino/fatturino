<?php

namespace App\Jobs;

use App\Services\DocumentMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDocumentMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $body,
        // SerializesModels resolves the model from DB when the job runs in the worker
        public readonly ?Model $document = null,
        public readonly bool $attachPdf = true,
        public readonly string $cc = '',
    ) {}

    /**
     * Apply SMTP overrides and send the mail synchronously inside the worker process,
     * so the user-configured SMTP settings (from DB) are used — not the .env defaults.
     */
    public function handle(DocumentMailer $mailer): void
    {
        $mailer->deliver($this->recipientEmail, $this->subject, $this->body, $this->document, $this->attachPdf, $this->cc);
    }
}
