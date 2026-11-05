<?php

namespace App\Jobs;

use App\Services\DocumentMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $body,
        public readonly Model $document,
        public readonly string $scenario,
        public readonly bool $attachPdf = true,
        public readonly string $cc = '',
        public readonly string $bcc = '',
    ) {}

    /**
     * Apply SMTP overrides and send the reminder inside the worker process,
     * so PDF generation and delivery do not block the HTTP request.
     */
    public function handle(DocumentMailer $mailer): void
    {
        try {
            $mailer->deliverPaymentReminder(
                $this->recipientEmail,
                $this->subject,
                $this->body,
                $this->document,
                $this->scenario,
                $this->attachPdf,
                $this->cc,
                $this->bcc,
            );
        } catch (\Throwable $e) {
            $mailer->recordPaymentReminderFailure(
                $this->document,
                $this->scenario,
                $this->recipientEmail,
                $this->subject,
                $e->getMessage(),
                $this->cc,
                $this->bcc,
            );

            throw $e;
        }
    }
}
