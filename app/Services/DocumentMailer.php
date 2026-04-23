<?php

namespace App\Services;

use App\Jobs\SendDocumentMailJob;
use App\Mail\DocumentMail;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DocumentMailer
{
    public function __construct(
        private readonly EmailSettings $emailSettings,
        private readonly CompanySettings $companySettings,
    ) {}

    /**
     * Send document email using default template for the given type.
     * Throws on delivery failure; callers handle the exception (toast, log, audit).
     */
    public function send(Model $document, string $recipientEmail): void
    {
        $type = $this->resolveDocumentType($document);
        $subject = $this->renderSubject($type, $document);
        $body = $this->renderBody($type, $document);

        SendDocumentMailJob::dispatch($recipientEmail, $subject, $body, $document);
    }

    /**
     * Send document email with caller-supplied subject and body (used from the modal).
     * Throws on delivery failure.
     */
    public function sendWithOverrides(Model $document, string $recipientEmail, string $subject, string $body, bool $attachPdf = true, string $cc = ''): void
    {
        SendDocumentMailJob::dispatch($recipientEmail, $subject, $body, $document, $attachPdf, $cc);
    }

    /**
     * Render the subject template for the given document type.
     */
    public function renderSubject(string $type, Model $document): string
    {
        $template = match ($type) {
            'sales' => $this->emailSettings->template_sales_subject,
            'proforma' => $this->emailSettings->template_proforma_subject,
            default => '',
        };

        return $this->replacePlaceholders($template, $document);
    }

    /**
     * Render the body template for the given document type.
     */
    public function renderBody(string $type, Model $document): string
    {
        $template = match ($type) {
            'sales' => $this->emailSettings->template_sales_body,
            'proforma' => $this->emailSettings->template_proforma_body,
            default => '',
        };

        return $this->replacePlaceholders($template, $document);
    }

    /**
     * Send a test email to the configured from_address to verify SMTP connectivity.
     * Returns null on success, or the error message string on failure.
     */
    public function testConnection(): ?string
    {
        $recipient = $this->emailSettings->from_address
            ?? config('mail.from.address');

        if (! $recipient) {
            return __('app.email.test_error_no_recipient');
        }

        try {
            $this->applySmtpOverrides();
            Mail::raw(__('app.email.test_body'), function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject(__('app.email.test_subject'));
            });

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Resolve the document type string from the model's 'type' attribute.
     */
    private function resolveDocumentType(Model $document): string
    {
        // The 'type' column is set by model booted() hooks
        return $document->getAttributes()['type'] ?? 'sales';
    }

    /**
     * Replace all supported placeholders in the template string.
     */
    private function replacePlaceholders(string $template, Model $document): string
    {
        $contact = $document->contact;

        $replacements = [
            '{CLIENTE}' => $contact?->name ?? '',
            '{NUMERO_DOCUMENTO}' => $document->number ?? '',
            '{DATA_DOCUMENTO}' => $document->date?->format('d/m/Y') ?? '',
            '{IMPORTO_NETTO}' => '€ '.number_format(($document->total_net ?? 0) / 100, 2, ',', '.'),
            '{IMPORTO_IVA}' => '€ '.number_format(($document->total_vat ?? 0) / 100, 2, ',', '.'),
            '{IMPORTO_TOTALE}' => '€ '.number_format(($document->total_gross ?? 0) / 100, 2, ',', '.'),
            '{AZIENDA}' => $this->companySettings->company_name,
            '{PARTITA_IVA_AZIENDA}' => $this->companySettings->company_vat_number,
            '{EMAIL_CLIENTE}' => $contact?->email ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Apply SMTP overrides and send synchronously. Called from SendDocumentMailJob
     * so it runs inside the queue worker process — where Config::set() actually takes effect.
     */
    public function deliver(string $recipientEmail, string $subject, string $body, ?Model $document = null, bool $attachPdf = true, string $cc = ''): void
    {
        $this->applySmtpOverrides();

        $attachedDocument = $attachPdf ? $document : null;

        Mail::to($recipientEmail)->send(new DocumentMail($subject, $body, $attachedDocument, $cc));
    }

    /**
     * Override the SMTP mailer config at runtime using user-configured settings.
     * Falls back to .env defaults when settings are not configured.
     */
    private function applySmtpOverrides(): void
    {
        // When SMTP is managed by env, .env MAIL_* vars win — never override from DB
        if (config('email.managed_by_env')) {
            return;
        }

        if ($this->emailSettings->smtp_host) {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $this->emailSettings->smtp_host);
            Config::set('mail.mailers.smtp.port', $this->emailSettings->smtp_port ?? 587);
            Config::set('mail.mailers.smtp.username', $this->emailSettings->smtp_username);
            Config::set('mail.mailers.smtp.password', $this->emailSettings->smtp_password);
            Config::set('mail.mailers.smtp.encryption', $this->emailSettings->smtp_encryption ?? 'tls');
        }

        if ($this->emailSettings->from_address) {
            Config::set('mail.from.address', $this->emailSettings->from_address);
        }

        if ($this->emailSettings->from_name) {
            Config::set('mail.from.name', $this->emailSettings->from_name);
        }

        // Purge the cached mailer so the new config takes effect immediately
        Mail::purge('smtp');
    }
}
