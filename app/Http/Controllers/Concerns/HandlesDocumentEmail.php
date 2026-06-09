<?php

namespace App\Http\Controllers\Concerns;

use App\Services\DocumentMailer;
use App\Services\PostHogTelemetryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

trait HandlesDocumentEmail
{
    protected function sendDocumentEmail(
        Request $request,
        Model $document,
        DocumentMailer $mailer,
        string $missingRecipientMessage
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'recipient_email' => 'nullable|email',
            'cc' => 'nullable|email',
            'subject' => 'nullable|string',
            'body' => 'nullable|string',
        ]);

        $recipientEmail = $validated['recipient_email'] ?? $document->contact?->email;
        $documentType = $document->getAttributes()['type'] ?? 'sales';
        $subject = $validated['subject'] ?? $mailer->renderSubject($documentType, $document);
        $body = $validated['body'] ?? $mailer->renderBody($documentType, $document);
        $cc = $validated['cc'] ?? '';

        if (! $recipientEmail) {
            if (! $request->expectsJson()) {
                return back()->withErrors(['recipient_email' => $missingRecipientMessage]);
            }

            return response()->json([
                'success' => false,
                'error' => $missingRecipientMessage,
            ], 422);
        }

        try {
            $mailer->sendWithOverrides($document, $recipientEmail, $subject, $body, true, $cc);
        } catch (Throwable $e) {
            if (! $request->expectsJson()) {
                return back()->withErrors(['action' => 'Invio email non riuscito: '.$e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Invio email non riuscito: '.$e->getMessage(),
            ], 500);
        }

        app(PostHogTelemetryService::class)->capture(
            'document_email_sent',
            app(PostHogTelemetryService::class)->documentProperties($document),
            $request->user()
        );

        if (! $request->expectsJson()) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Operazione completata',
                'message' => 'Email accodata correttamente.',
                'duration' => 4500,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email accodata correttamente.',
        ]);
    }

    protected function documentEmailPreview(
        Model $document,
        DocumentMailer $mailer
    ): JsonResponse {
        $documentType = $document->getAttributes()['type'] ?? 'sales';
        $metadata = is_array($document->metadata) ? $document->metadata : [];

        return response()->json([
            'success' => true,
            'preview' => [
                'recipient_email' => $document->contact?->email ?? '',
                'cc' => (string) data_get($metadata, 'email.cc', ''),
                'subject' => $mailer->renderSubject($documentType, $document),
                'body' => $mailer->renderBody($documentType, $document),
            ],
        ]);
    }
}
