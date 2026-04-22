<?php

namespace App\Enums;

enum MenuItem: string
{
    case Dashboard = 'dashboard';
    case Sales = 'sales';
    case SellInvoices = 'sell-invoices';
    case PurchaseInvoices = 'purchase-invoices';
    case Purchases = 'purchases';
    case SelfInvoices = 'self-invoices';
    case CreditNotes = 'credit-notes';
    case Proforma = 'proforma';
    case Contacts = 'contacts';
    case Configuration = 'configuration';
    case Sequences = 'sequences';
    case CompanySettings = 'company-settings';
    case InvoiceSettings = 'invoice-settings';
    case ElectronicInvoiceSettings = 'electronic-invoice-settings';
    case EmailSettings = 'email-settings';
    case Services = 'services';
    case Imports = 'imports';
    case Plugins = 'plugins';
    case AuditLog = 'audit-log';
}
