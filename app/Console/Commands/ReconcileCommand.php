<?php

namespace App\Console\Commands;

use App\Enums\SdiStatus;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;
use App\Services\InvoiceXmlImportService;
use App\Services\OpenApiSdiService;
use App\Settings\InvoiceSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileCommand extends Command
{
    protected $signature = 'openapi:reconcile
        {--receive-only : Only reconcile missed supplier invoices}
        {--updates-only : Only reconcile missed status updates}
        {--days=7 : Number of days to look back for missed invoices}
        {--max-pages=500 : Hard limit on pages fetched to guard against API pagination bugs}
        {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Reconcile missed webhook events by polling the OpenAPI SDI API';

    // Priority of SDI statuses for choosing the most advanced notification
    private const STATE_PRIORITY = [
        'sent' => 1,
        'delivered' => 2,
        'not_delivered' => 2,
        'rejected' => 3,
        'accepted' => 4,
        'refused' => 4,
        'expired' => 4,
    ];

    public function handle(OpenApiSdiService $service): int
    {
        if (! $service->isConfigured()) {
            $this->error('OpenAPI SDI is not configured.');

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->info('[DRY RUN] No changes will be made.');
        }

        $receiveStats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $updateStats = ['checked' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

        if (! $this->option('updates-only')) {
            $receiveStats = $this->reconcileReceive($service, $isDryRun);
        }

        if (! $this->option('receive-only')) {
            $updateStats = $this->reconcileUpdates($service, $isDryRun);
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Supplier invoices imported', $receiveStats['imported']],
            ['Supplier invoices skipped', $receiveStats['skipped']],
            ['Supplier invoice errors', $receiveStats['errors']],
            ['Status updates checked', $updateStats['checked']],
            ['Status updates applied', $updateStats['updated']],
            ['Status updates unchanged', $updateStats['unchanged']],
            ['Status update errors', $updateStats['errors']],
        ]);

        return self::SUCCESS;
    }

    private function reconcileReceive(OpenApiSdiService $service, bool $isDryRun): array
    {
        $this->info('Reconciling supplier invoices...');

        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $days = (int) $this->option('days');
        $maxPages = (int) $this->option('max-pages');
        $cutoffDate = Carbon::now()->subDays($days);
        $page = 1;
        $pageSize = 100;

        while ($page <= $maxPages) {
            $result = $service->getSupplierInvoices([
                'page' => $page,
                'per_page' => $pageSize,
            ]);

            if (! $result['success']) {
                $this->error("Failed to fetch supplier invoices page {$page}: ".($result['error'] ?? 'unknown'));
                $stats['errors']++;

                break;
            }

            $records = $result['data'];
            if (empty($records)) {
                break;
            }

            // OpenAPI has no server-side date filter; apply cutoff client-side using created_at
            $allOlderThanCutoff = true;

            foreach ($records as $record) {
                $receivedAt = isset($record['created_at']) ? Carbon::parse($record['created_at']) : null;

                if ($receivedAt && $receivedAt->lt($cutoffDate)) {
                    $stats['skipped']++;

                    continue;
                }

                $allOlderThanCutoff = false;
                $uuid = $record['uuid'] ?? null;

                if (empty($uuid)) {
                    $stats['errors']++;

                    continue;
                }

                // Idempotency: skip if this UUID was already linked to a purchase or self-invoice
                if (PurchaseInvoice::withoutGlobalScopes()->where('sdi_uuid', $uuid)->exists()) {
                    $stats['skipped']++;

                    continue;
                }

                if (SelfInvoice::withoutGlobalScopes()->where('sdi_uuid', $uuid)->exists()) {
                    $stats['skipped']++;

                    continue;
                }

                if ($isDryRun) {
                    $filename = $record['sdi_file_name'] ?? $record['filename'] ?? $uuid;
                    $this->line("  [DRY RUN] Would import: {$filename} (UUID: {$uuid})");
                    $stats['imported']++;

                    continue;
                }

                $downloadResult = $service->downloadInvoiceXml($uuid);
                if (! $downloadResult['success']) {
                    $this->warn("  Failed to download invoice {$uuid}: ".($downloadResult['error'] ?? 'unknown'));
                    $stats['errors']++;

                    continue;
                }

                try {
                    // If this matches a self-invoice, reconcile only and skip import
                    if ($this->handleSelfInvoiceDelivery($record, $downloadResult['xml'])) {
                        $fn = $record['sdi_file_name'] ?? $record['filename'] ?? $uuid;
                        $this->line("  Skipped (self-invoice): {$fn}");
                        $stats['skipped']++;

                        continue;
                    }

                    $invoiceSettings = app(InvoiceSettings::class);
                    $sequenceId = $invoiceSettings->default_sequence_purchase ?: null;

                    $importService = app(InvoiceXmlImportService::class);
                    $importService->importXml($downloadResult['xml'], $sequenceId, 'purchase', [
                        'sdi_uuid' => $uuid,
                        'sdi_file_id' => $record['file_id'] ?? null,
                        'sdi_filename' => $record['sdi_file_name'] ?? $record['filename'] ?? null,
                        'sdi_received_at' => $record['created_at'] ?? null,
                        'sdi_payload' => $record['payload'] ?? null,
                    ]);

                    // importXml swallows exceptions internally — check for errors explicitly
                    $importErrors = $importService->getErrors();
                    if (! empty($importErrors)) {
                        $errorMessage = implode('; ', $importErrors);
                        $this->warn("  Import failed for {$uuid}: {$errorMessage}");
                        $stats['errors']++;

                        Log::channel('fe-openapi')->error('OpenAPI reconcile: supplier invoice import failed', [
                            'uuid' => $uuid,
                            'error' => $errorMessage,
                            'xml_preview' => substr($downloadResult['xml'], 0, 200),
                        ]);

                        continue;
                    }

                    $importStats = $importService->getStats();
                    $filename = $record['sdi_file_name'] ?? $record['filename'] ?? $uuid;

                    if (($importStats['invoices_imported'] ?? 0) < 1) {
                        $this->line("  Skipped (already imported): {$filename}");
                        $stats['skipped']++;

                        continue;
                    }

                    $this->line("  Imported: {$filename} (UUID: {$uuid})");
                    $stats['imported']++;

                    Log::channel('fe-openapi')->info('OpenAPI reconcile: supplier invoice imported', [
                        'uuid' => $uuid,
                        'file_name' => $filename,
                    ]);
                } catch (\Exception $e) {
                    $this->warn("  Import failed for {$uuid}: {$e->getMessage()}");
                    $stats['errors']++;

                    Log::channel('fe-openapi')->error('OpenAPI reconcile: supplier invoice import failed', [
                        'uuid' => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($allOlderThanCutoff) {
                break;
            }

            // Stop when API returns a partial page (last page)
            if (count($records) < $pageSize) {
                break;
            }

            $page++;
        }

        if ($page > $maxPages) {
            $this->warn("Reached max-pages limit ({$maxPages}). Check for API pagination issues.");
            Log::channel('fe-openapi')->warning('OpenAPI reconcile: reached max-pages limit', ['max_pages' => $maxPages]);
        }

        return $stats;
    }

    private function reconcileUpdates(OpenApiSdiService $service, bool $isDryRun): array
    {
        $this->info('Reconciling status updates...');

        $stats = ['checked' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

        // OpenAPI UUIDs contain dashes; filter to avoid matching Invoicetronic numeric IDs
        $invoices = FiscalDocument::withoutGlobalScopes()
            ->whereNotNull('sdi_uuid')
            ->where('sdi_uuid', 'LIKE', '%-%')
            ->whereIn('sdi_status', [
                SdiStatus::Sent->value,
                SdiStatus::Delivered->value,
                SdiStatus::NotDelivered->value,
            ])
            ->get();

        $stats['checked'] = $invoices->count();

        if ($stats['checked'] === 0) {
            $this->line('  No invoices pending status updates.');

            return $stats;
        }

        foreach ($invoices as $invoice) {
            $result = $service->getInvoiceNotifications($invoice->sdi_uuid);

            if (! $result['success']) {
                $this->warn("  Failed to fetch notifications for invoice #{$invoice->id}: ".($result['error'] ?? 'unknown'));
                $stats['errors']++;

                continue;
            }

            $notifications = $result['notifications'] ?? [];
            if (empty($notifications)) {
                $stats['unchanged']++;

                continue;
            }

            $bestNotification = $this->pickMostAdvancedNotification($notifications);
            if (! $bestNotification) {
                $stats['unchanged']++;

                continue;
            }

            $notificationType = $bestNotification['type'] ?? null;
            $newStatus = $notificationType ? SdiStatus::fromNotificationType($notificationType) : null;

            if (! $newStatus) {
                $stats['unchanged']++;

                continue;
            }

            if ($invoice->sdi_status === $newStatus) {
                $stats['unchanged']++;

                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY RUN] Invoice #{$invoice->id}: {$invoice->sdi_status->value} → {$newStatus->value}");
                $stats['updated']++;

                continue;
            }

            $message = $this->buildNotificationMessage($notificationType, $bestNotification);

            $invoice->update([
                'sdi_status' => $newStatus->value,
                'sdi_message' => $message,
            ]);

            // Create outbound log only if not already recorded (idempotency on fiscal_document_id + event_type + status)
            $logExists = EiOutboundLog::where('fiscal_document_id', $invoice->id)
                ->where('event_type', $notificationType)
                ->where('status', $newStatus->value)
                ->exists();

            if (! $logExists) {
                EiOutboundLog::create([
                    'fiscal_document_id' => $invoice->id,
                    'event_type' => $notificationType,
                    'status' => $newStatus->value,
                    'message' => $message,
                    'raw_payload' => ['source' => 'reconcile', 'notification' => $bestNotification],
                ]);
            }

            $this->line("  Updated invoice #{$invoice->id}: {$invoice->sdi_status->value} → {$newStatus->value}");
            $stats['updated']++;

            Log::channel('fe-openapi')->info('OpenAPI reconcile: invoice status updated', [
                'fiscal_document_id' => $invoice->id,
                'sdi_uuid' => $invoice->sdi_uuid,
                'old_status' => $invoice->sdi_status->value,
                'new_status' => $newStatus->value,
            ]);
        }

        return $stats;
    }

    /**
     * Pick the notification with the highest state priority.
     * Ties broken by most recent date.
     */
    private function pickMostAdvancedNotification(array $notifications): ?array
    {
        $best = null;
        $bestPriority = 0;
        $bestDate = null;

        foreach ($notifications as $notification) {
            $type = $notification['type'] ?? null;
            $status = $type ? SdiStatus::fromNotificationType($type) : null;

            if (! $status) {
                continue;
            }

            $priority = self::STATE_PRIORITY[$status->value] ?? 0;
            $date = isset($notification['created_at']) ? Carbon::parse($notification['created_at']) : null;

            if ($priority > $bestPriority || ($priority === $bestPriority && $date && $bestDate && $date->gt($bestDate))) {
                $best = $notification;
                $bestPriority = $priority;
                $bestDate = $date;
            }
        }

        return $best;
    }

    /**
     * Check whether the incoming supplier invoice matches a self-invoice we
     * previously sent. When a self-invoice is sent to SDI and then received
     * back as a purchase (we are also the recipient), we must NOT import it
     * as a duplicate purchase — instead mark the self-invoice as delivered.
     *
     * Returns true if handled (caller should skip this record), false if
     * this is a genuine supplier invoice that should be imported normally.
     */
    /**
     * If the incoming supplier invoice matches a self-invoice we previously
     * sent, mark it as Delivered and tell the caller to skip the import
     * (no duplicate purchase row).
     */
    private function handleSelfInvoiceDelivery(array $record, string $xml): bool
    {
        // Only self-invoice document types
        $documentType = $this->extractXmlField($xml, 'TipoDocumento');

        if (! $documentType || ! in_array($documentType, ['TD17', 'TD18', 'TD19', 'TD28', 'TD29'], true)) {
            return false;
        }

        $documentNumber = $this->extractXmlField($xml, 'Numero');

        if (! $documentNumber) {
            return false;
        }

        $selfInvoice = SelfInvoice::withoutGlobalScopes()
            ->where('number', $documentNumber)
            ->first();

        if (! $selfInvoice) {
            return false;
        }

        $fileId = $record['file_id'] ?? null;
        $filename = $record['sdi_file_name'] ?? $record['filename'] ?? null;
        $uuid = $record['uuid'] ?? null;

        $selfInvoice->update([
            'sdi_uuid' => $selfInvoice->sdi_uuid ?: $uuid,
            'sdi_file_id' => $selfInvoice->sdi_file_id ?: $fileId,
            'sdi_filename' => $selfInvoice->sdi_filename ?: $filename,
            'sdi_status' => SdiStatus::Delivered,
            'sdi_message' => 'Consegnata (ricevuta come acquisto)',
            'sdi_primary_channel' => 'inbound',
        ]);

        Log::channel('fe-openapi')->info('OpenAPI reconcile: self-invoice marked as delivered (number match)', [
            'self_invoice_id' => $selfInvoice->id,
            'number' => $documentNumber,
            'file_id' => $fileId,
        ]);

        return true;
    }

    /**
     * Extract a field value from FatturaPA XML using a simple regex.
     * Avoids namespace complexity of SimpleXML - just find <TagName>value</TagName>.
     */
    private function extractXmlField(string $xml, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'[^>]*>([^<]+)<\/'.$tag.'>/', $xml, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Build a human-readable message from SDI notification data.
     */
    private function buildNotificationMessage(string $type, array $notification): string
    {
        $descriptions = [
            'NS' => 'Notifica di Scarto',
            'RC' => 'Ricevuta di Consegna',
            'MC' => 'Mancata Consegna',
            'DT' => 'Decorrenza Termini',
            'NE' => 'Esito Committente',
            'AT' => 'Attestazione',
            'EC' => 'Esito Cessionario',
        ];

        $message = $descriptions[$type] ?? $type;

        $errorList = $notification['message']['lista_errori']['Errore'] ?? null;
        if ($errorList) {
            $code = $errorList['Codice'] ?? '';
            $desc = $errorList['Descrizione'] ?? '';
            if ($code || $desc) {
                $message .= " — {$code}: {$desc}";
            }
        }

        $sdiId = $notification['message']['identificativo_sdi'] ?? null;
        if ($sdiId) {
            $message .= " (SDI: {$sdiId})";
        }

        return $message;
    }
}
