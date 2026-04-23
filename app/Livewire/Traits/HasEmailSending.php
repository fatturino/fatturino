<?php

namespace App\Livewire\Traits;

use App\Services\DocumentMailer;
use App\Settings\EmailSettings;
use App\Support\InvoiceAuditDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provides email sending functionality to Livewire Edit components.
 *
 * The host component must implement:
 *   - getEmailDocument(): Model  — returns the document being emailed
 *   - getEmailDocumentType(): string  — returns 'sales' or 'proforma'
 */
trait HasEmailSending
{
    public bool $emailModal = false;

    public string $emailRecipient = '';

    public string $emailCc = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public bool $emailAttachPdf = true;

    /**
     * Open the email modal pre-filled with the contact email and rendered template.
     */
    public function openEmailModal(): void
    {
        $document = $this->getEmailDocument();
        $contact = $document->contact;
        $type = $this->getEmailDocumentType();

        $this->emailRecipient = $contact?->email ?? '';
        $this->emailCc = '';
        $this->emailAttachPdf = true;

        $mailer = app(DocumentMailer::class);
        $this->emailSubject = $mailer->renderSubject($type, $document);
        $this->emailBody = $mailer->renderBody($type, $document);

        $this->emailModal = true;
    }

    /**
     * Validate and send the email, then audit the result and show a toast.
     */
    public function sendEmail(): void
    {
        $this->validate([
            'emailRecipient' => 'required|email',
            'emailCc' => 'nullable|email',
            'emailSubject' => 'required|string|max:255',
            'emailBody' => 'required|string',
        ]);

        $document = $this->getEmailDocument();
        $this->emailModal = false;

        try {
            app(DocumentMailer::class)->sendWithOverrides(
                $document,
                $this->emailRecipient,
                $this->emailSubject,
                $this->emailBody,
                $this->emailAttachPdf,
                $this->emailCc,
            );

            InvoiceAuditDispatcher::dispatch($document, 'email_sent');
            $this->success(__('app.email.sent_success'));
        } catch (Throwable $e) {
            Log::error('Document email send failed', [
                'document_id' => $document->getKey(),
                'error' => $e->getMessage(),
            ]);
            $this->error(__('app.email.send_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Trigger automatic email send after key actions (SDI send, status change).
     * Only sends when the contact has an email and auto-send is enabled.
     */
    protected function triggerAutoSend(string $settingKey): void
    {
        $settings = app(EmailSettings::class);

        if (! $settings->{$settingKey}) {
            return;
        }

        $document = $this->getEmailDocument();
        $recipientEmail = $document->contact?->email;

        if (! $recipientEmail) {
            return;
        }

        try {
            app(DocumentMailer::class)->send($document, $recipientEmail);
            InvoiceAuditDispatcher::dispatch($document, 'email_sent');
        } catch (Throwable $e) {
            Log::error('Auto-send email failed', [
                'document_id' => $document->getKey(),
                'setting' => $settingKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns the document model to be emailed.
     * Must be implemented by the host component.
     */
    abstract protected function getEmailDocument(): Model;

    /**
     * Returns the document type string: 'sales' or 'proforma'.
     * Must be implemented by the host component.
     */
    abstract protected function getEmailDocumentType(): string;
}
