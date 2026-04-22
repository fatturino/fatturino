<?php

namespace App\Enums;

/**
 * Actions that can be restricted by EnvironmentCapabilities.
 *
 * Plugins check these values to decide what to block (e.g., demo mode).
 * Using an enum gives IDE autocomplete and prevents typos in action strings.
 */
enum Capability: string
{
    // Settings
    case EditCompanySettings = 'edit-company-settings';
    case EditInvoiceSettings = 'edit-invoice-settings';
    case EditSdiSettings = 'edit-sdi-settings';
    case EditEmailSettings = 'edit-email-settings';

    // Email sending
    case SendDocumentEmail = 'send-document-email';

    // Invoices
    case ManageSalesInvoices = 'manage-sales-invoices';
    case ManagePurchaseInvoices = 'manage-purchase-invoices';
    case ManageSelfInvoices = 'manage-self-invoices';
    case SendToSdi = 'send-to-sdi';

    // Master data
    case ManageContacts = 'manage-contacts';
    case ManageVatRates = 'manage-vat-rates';
    case ManageSequences = 'manage-sequences';

    // Data operations
    case ImportData = 'import-data';

    // Services
    case ManageBackupSettings = 'manage-backup-settings';
    case ManageMonitoringSettings = 'manage-monitoring-settings';
}
