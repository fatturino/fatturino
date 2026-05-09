<?php

return [

    'common' => [
        'search' => 'Search...',
        'filters' => 'Filters',
        'create' => 'Create',
        'reset' => 'Reset',
        'done' => 'Done',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'confirm_delete' => 'Delete? This action cannot be undone.',
        'confirm_title' => 'Confirm',
        'confirm' => 'Confirm',
        'filters_cleared' => 'Filters cleared.',
        'logoff' => 'Log off',
        'goodbye' => 'Item removed',
        'unknown' => 'Unknown',
        'delete' => 'Delete',
        'collapse' => 'Collapse',
        'select' => 'Select...',
        'all' => 'All',
        'close' => 'Close',
        'empty_table' => 'No items yet. Create your first one.',
        'skip_to_content' => 'Skip to main content',
        'home' => 'Home',
    ],

    'nav' => [
        'open_menu' => 'Open menu',
        'dashboard' => 'Dashboard',
        'sales' => 'Sales',
        'sell_invoices' => 'Invoices',
        'credit_notes' => 'Credit Notes',
        'proforma' => 'Proforma',
        'purchases' => 'Purchases',
        'purchase_invoices' => 'Invoices',
        'self_invoices' => 'Self-Invoices',
        'imports' => 'Imports',
        'contacts' => 'Customers',
        'products' => 'Products',
        'configuration' => 'Configuration',
        'sequences' => 'Sequences',
        'vat_rates' => 'VAT Rates',
        'company_settings' => 'Company Settings',
        'invoice_settings' => 'Invoice Settings',
        'electronic_invoice_settings' => 'E-Invoice Settings',
        'email_settings' => 'Email Settings',
        'services' => 'Services',
        'plugins' => 'Plugins',
        'audit_log' => 'Audit Log',
    ],

    'audit' => [
        'timeline_empty' => 'No activity recorded.',
        'grouped_line_changes' => ':count line edits',
        'expand' => 'Expand',
        'collapse' => 'Collapse',
        'system' => 'System',
        'tab_details' => 'Details',
        'tab_history' => 'History',
        'events' => [
            'created' => 'Invoice created and saved as draft.',
            'updated' => 'Invoice updated.',
            'deleted' => 'Invoice permanently deleted.',
            'restored' => 'Document restored',
            'email_sent' => 'Email sent',
            'sdi_sent' => 'Sent to SDI',
            'sdi_accepted' => 'Accepted by SDI',
            'sdi_rejected' => 'Rejected by SDI',
            'sdi_sent_event' => 'SDI submission',
            'sdi_error' => 'SDI error',
        ],
        'index' => [
            'title' => 'Audit Log',
            'filter_user' => 'User',
            'filter_event' => 'Event',
            'filter_entity' => 'Entity',
            'filter_from' => 'From',
            'filter_to' => 'To',
            'column_date' => 'Date',
            'column_user' => 'User',
            'column_event' => 'Event',
            'column_entity' => 'Entity',
            'column_actions' => 'Actions',
            'details' => 'Details',
            'no_changes' => 'No changes recorded.',
            'old_value' => 'Previous',
            'new_value' => 'New',
        ],
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'recent_invoices' => 'Recent Invoices',
        'welcome' => 'Welcome to your dashboard!',

        // KPI stat cards
        'stat_revenue_month' => 'Revenue (month)',
        'stat_revenue_ytd' => 'Revenue (year)',
        'stat_revenue_dec' => 'Revenue (Dec. :year)',
        'stat_revenue_year' => 'Revenue :year',
        'stat_invoices_month' => 'Invoices issued (month)',
        'stat_active_clients' => 'Active clients (year)',
        'vs_last_month' => 'vs last month',
        'vs_nov' => 'vs Nov. :year',
        'invoices_issued' => 'invoices issued',
        'avg_invoice' => 'Avg:',
        'total_contacts' => 'total contacts',

        // Top clients section
        'top_clients_title' => 'Top Clients (year)',
        'no_data' => 'No data available',

        // Fiscal summary
        'vat_collected_ytd' => 'VAT Collected (year)',
        'withholding_ytd' => 'Withholding Tax (year)',
        'year_to_date' => 'year to date',
        'full_year' => 'full year :year',

        // Read-only banner for past fiscal years
        'readonly_year_title' => 'Fiscal year :year — read only',

        // Recent invoices
        'view_all' => 'View all',
        'no_invoices' => 'No invoices yet',

        // Payment widget
        'payments_title' => 'Payment Status',
        'payments_unpaid' => 'Outstanding',
        'payments_partial' => 'Partial',
        'payments_paid' => 'Collected',
        'payments_overdue' => 'overdue',
        'payments_upcoming' => 'Upcoming due dates',

        // VAT Balance widget
        'vat_balance_title' => 'VAT Balance',
        'vat_collected_label' => 'Collected (sales)',
        'vat_on_purchases_label' => 'On purchases',
        'vat_balance_label' => 'Balance',
        'vat_balance_owed' => 'to pay',
        'vat_balance_credit' => 'credit',

        // Cashflow widget
        'cashflow_title' => 'Cash Flow Forecast',
        'cashflow_inflows' => 'Inflows',
        'cashflow_outflows' => 'Outflows',
        'cashflow_net' => 'Net',
        'overdue' => 'Overdue',
        'no_cashflow_data' => 'No invoices with due dates in the next 6 months.',
    ],

    'purchase_invoices' => [
        'title' => 'Purchases',
        'create_title' => 'Record Purchase',
        'edit_title' => 'Edit Purchase #:number',

        'header_section' => 'Header',
        'sequence' => 'Sequence',
        'number' => 'Number',
        'date' => 'Date',
        'supplier' => 'Supplier',
        'select_supplier' => 'Select a supplier',

        'lines_section' => 'Lines',
        'line_description' => 'Description',
        'line_quantity' => 'Qty',
        'line_unit_of_measure' => 'UoM',
        'line_price' => 'Price',
        'line_vat' => 'VAT',
        'add_line' => 'Add Line',
        'draft_saved' => 'Draft saved at',
        'no_lines' => 'Add the first invoice line.',

        'totals_section' => 'Totals',
        'net_total' => 'Net Total',
        'vat_total' => 'VAT',
        'grand_total' => 'Total',

        'status_draft' => 'Draft',
        'status_generated' => 'Recorded',
        'status_sent' => 'Booked',
        'status_received' => 'Received from SDI',

        // Listing stats
        'stat_total_invoices' => 'Purchases',
        'stat_total_amount' => 'Total expenses',
        'stat_unpaid' => 'Unpaid',
        'stat_overdue' => 'Overdue',
        'filter_status' => 'Invoice status',
        'filter_payment' => 'Payment status',

        // Table column headers
        'col_number' => 'Number',
        'col_date' => 'Date',
        'col_supplier' => 'Supplier',
        'col_total' => 'Total',
        'col_status' => 'Status',

        // Toast messages
        'created' => 'Purchase recorded.',
        'updated' => 'Purchase updated.',
        'deleted' => 'Purchase #:number deleted',
        'cannot_delete_sdi' => 'Cannot delete an invoice received from SDI.',
        'filters_cleared' => 'Filters cleared.',
        'readonly_error' => 'This fiscal year is closed. Purchases cannot be modified.',
        'readonly_banner' => 'Fiscal year :year closed — read only',
        'sdi_received_banner' => 'Invoice received from SDI — read only',
        'import_only_alert' => 'Purchase invoices are automatically imported via the import feature or the Electronic Invoicing webhook. They cannot be created manually.',
    ],

    'invoices' => [
        'title' => 'Invoices',
        'create_title' => 'Create Invoice',
        'edit_title' => 'Edit Invoice #:number',

        'header_section' => 'Header',
        'sequence' => 'Sequence',
        'number' => 'Number',
        'date' => 'Date',
        'customer' => 'Customer',
        'select_customer' => 'Select a customer',

        'lines_section' => 'Lines',
        'line_description' => 'Description',
        'line_quantity' => 'Qty',
        'line_unit_of_measure' => 'UoM',
        'line_price' => 'Price',
        'line_vat' => 'VAT',
        'add_line' => 'Add Line',
        'draft_saved' => 'Draft saved at',
        'no_lines' => 'Add the first invoice line.',

'tax_options_section' => 'Tax Options',
        'no_tax_options_active' => 'No tax options',
        'totals_section' => 'Totals',
        'net_total' => 'Net Total',
        'vat_total' => 'VAT',
        'grand_total' => 'Total',

        // Withholding tax (Ritenuta d'acconto)
        'withholding_tax_label' => 'Subject to Withholding Tax',
        'withholding_tax_percent_label' => 'Withholding Rate',
        'withholding_tax_amount_label' => 'Withholding Tax (:percent%)',
        'net_due' => 'Net Amount Due',

        // Professional fund (Cassa Previdenziale)
        'fund_label' => 'Professional Fund',
        'fund_type_label' => 'Fund Type',
        'fund_percent_label' => 'Contribution Rate',
        'fund_vat_rate_label' => 'VAT Rate on Contribution',
        'fund_amount_label' => 'Fund Contribution (:percent%)',

        // Stamp duty (Marca da bollo)
        'stamp_duty_label' => 'Virtual Stamp Duty (€ 2.00)',
        'stamp_duty_hint' => 'Stamp duty of € 2.00 applied for amounts exceeding the threshold',

        'download_pdf' => 'Download PDF',
        'pdf_generation_error' => 'PDF generation error: :error',
        'download_xml' => 'Download XML',
        'sending_to_sdi' => 'Sending to SDI…',
        'sending_to_sdi_desc' => 'Generating the XML file and submitting it to the Italian Exchange System. This usually takes a few seconds.',
        'duplicated' => 'Invoice duplicated. You can now edit and save it.',
        'duplicate' => 'Duplicate',
        'send_to_sdi' => 'Send to SDI',
        'sent_to_sdi' => 'Sent to SDI',

        'status_draft' => 'Draft',
        'status_generated' => 'Generated',
        'status_sent' => 'Sent',

        // Listing stats
        'stat_total_invoices' => 'Invoices',
        'stat_total_amount' => 'Revenue',
        'stat_unpaid' => 'Unpaid',
        'stat_overdue' => 'Overdue',
        'filter_status' => 'Invoice status',
        'filter_payment' => 'Payment status',

        // Table column headers
        'col_number' => 'Number',
        'col_date' => 'Date',
        'col_customer' => 'Customer',
        'col_total' => 'Total',
        'col_status' => 'Status',

        // Toast messages
        'created' => 'Invoice created.',
        'updated' => 'Invoice updated.',
        'deleted' => 'Invoice #:number deleted',
        'filters_cleared' => 'Filters cleared.',
        'readonly_error' => 'This fiscal year is closed. Invoices cannot be modified.',
        'sdi_locked_banner' => 'Invoice submitted to SDI — read only',
        'save_before_send' => 'Save your changes before sending.',
        'openapi_not_configured' => 'OpenAPI SDI not configured. Go to Settings > OpenAPI.',
        'xml_invalid' => 'The invoice is not valid for SDI: :errors. Fix the indicated fields and try again.',
        'confirm_send_sdi' => 'Confirm sending this invoice to SDI?',
        'sent_success' => 'Invoice sent to SDI. You will be notified when it is processed.',
        'send_error' => 'SDI send error: :error',
        'generation_error' => 'A technical error occurred while generating the file. Please try again or contact support.',

        'sdi_not_configured_hint' => 'Enable Electronic Invoicing in settings to validate and send to SDI.',

        // InvoiceStatus enum labels
        'invoice_status_draft' => 'Draft',
        'invoice_status_generated' => 'Generated',
        'invoice_status_xml_validated' => 'XML Validated',
        'invoice_status_sent' => 'Sent',

        // PaymentStatus enum labels
        'payment_status_unpaid' => 'Unpaid',
        'payment_status_partial' => 'Partial',
        'payment_status_paid' => 'Paid',
        'payment_status_overdue' => 'Overdue',

        // Payment section
        'col_payment' => 'Payment',
        'payment_section' => 'Payment',
        'due_date' => 'Due Date',
        'paid_at' => 'Payment Date',
        'paid_amount' => 'Paid Amount',
        'save_payment' => 'Save Payment',
        'payment_saved' => 'Payment saved.',

        // XML validation
        'validate_xml' => 'Validate XML',
        'xml_validated_success' => 'XML validated successfully.',
        'confirm_validate_xml' => 'Confirm XML validation?',
        'cannot_send_not_validated' => 'XML must be validated before sending to SDI.',

        // XML validation error messages
        'xml_errors' => [
            'missing_header' => 'Electronic invoice header is missing',
            'missing_body' => 'Electronic invoice body is missing',
            'missing_sender_id' => 'Sender ID is missing',
            'missing_progressive' => 'Progressive sending number is missing',
            'missing_format' => 'Transmission format is missing',
            'missing_recipient' => 'Both recipient code and PEC are missing: at least one is required',
            'missing_transmission_data' => 'Transmission data is missing',
            'parsing_error' => 'XML parsing error: :error',
            'missing_customer_fiscal_id' => 'Customer has no valid VAT number or fiscal code (11-16 characters): update the contact before sending.',
        ],

        // SDI status labels
        'sdi_sent' => 'Sent',
        'sdi_rejected' => 'Rejected',
        'sdi_delivered' => 'Delivered',
        'sdi_not_delivered' => 'Not Delivered',
        'sdi_expired' => 'Deadline Expired',
        'sdi_accepted' => 'Accepted',
        'sdi_refused' => 'Refused',
        'sdi_error' => 'Error',
        'sdi_received' => 'Received from SDI',

        // SDI log
        'sdi_log_title' => 'SDI Log',
        'sdi_log_received_by_sdi' => 'Invoice received by SDI',
    ],

    'proforma' => [
        'title' => 'Proforma',
        'create_title' => 'New Proforma',
        'edit_title' => 'Proforma #:number',

        'header_section' => 'Header',
        'sequence' => 'Sequence',
        'number' => 'Number',
        'date' => 'Date',
        'customer' => 'Customer',
        'select_customer' => 'Select a customer',

        'lines_section' => 'Lines',
        'line_description' => 'Description',
        'line_quantity' => 'Qty',
        'line_unit_of_measure' => 'UoM',
        'line_price' => 'Price',
        'line_vat' => 'VAT',
        'add_line' => 'Add Line',
        'draft_saved' => 'Draft saved at',
        'no_lines' => 'No lines. Add a line to get started.',

'tax_options_section' => 'Tax Options',
        'no_tax_options_active' => 'No tax options',
        'totals_section' => 'Totals',
        'net_total' => 'Net Total',
        'vat_total' => 'VAT',
        'grand_total' => 'Grand Total',

        'withholding_tax_label' => 'Subject to Withholding Tax',
        'withholding_tax_percent_label' => 'Withholding Percentage',
        'withholding_tax_amount_label' => 'Withholding Tax (:percent%)',
        'net_due' => 'Net Due',

        'fund_label' => 'Professional Fund',
        'fund_type_label' => 'Fund Type',
        'fund_percent_label' => 'Fund Rate',
        'fund_vat_rate_label' => 'Fund VAT Rate',
        'fund_amount_label' => 'Professional Fund (:percent%)',

        'stamp_duty_label' => 'Stamp Duty (€ 2.00)',
        'stamp_duty_hint' => 'Stamp duty of € 2.00 applied for amounts above threshold',

        'status_draft' => 'Draft',
        'status_sent' => 'Sent',
        'status_converted' => 'Converted',
        'status_cancelled' => 'Cancelled',

        'stat_total' => 'Proforma',
        'stat_total_amount' => 'Total Amount',
        'stat_unpaid' => 'Unpaid',
        'stat_converted' => 'Converted',
        'filter_status' => 'Proforma status',
        'filter_payment' => 'Payment status',

        'col_number' => 'Number',
        'col_date' => 'Date',
        'col_customer' => 'Customer',
        'col_total' => 'Total',
        'col_status' => 'Status',
        'col_payment' => 'Payment',

        'mark_as_sent' => 'Mark as Sent',
        'convert_to_invoice' => 'Convert to Invoice',
        'cancel_proforma' => 'Cancel Proforma',
        'confirm_convert' => 'Convert this proforma to an electronic invoice?',
        'confirm_cancel' => 'Cancel this proforma?',
        'confirm_mark_sent' => 'Mark this proforma as sent?',

        'created' => 'Proforma created.',
        'updated' => 'Proforma updated.',
        'deleted' => 'Proforma #:number deleted.',
        'marked_sent' => 'Proforma marked as sent.',
        'converted_success' => 'Proforma converted to invoice #:number.',
        'cancelled' => 'Proforma cancelled.',
        'already_converted' => 'This proforma has already been converted.',
        'cannot_convert' => 'Cannot convert: proforma must be in Draft or Sent status.',
        'readonly_error' => 'This fiscal year is closed. Proforma invoices cannot be modified.',
        'readonly_banner' => 'Fiscal year :year closed, read-only',
        'converted_banner' => 'Proforma converted to invoice',
        'cancelled_banner' => 'Proforma cancelled',
        'filters_cleared' => 'Filters cleared.',

        'payment_section' => 'Payment',
        'due_date' => 'Due Date',
        'paid_at' => 'Payment Date',
        'paid_amount' => 'Paid Amount',
        'save_payment' => 'Save Payment',
        'payment_saved' => 'Payment saved.',

        'payment_status_unpaid' => 'Unpaid',
        'payment_status_partial' => 'Partial',
        'payment_status_paid' => 'Paid',
        'payment_status_overdue' => 'Overdue',

        'reverse_calc_title' => 'Reverse Calculation',
        'reverse_calc_desired_net' => 'Desired Net Due',
        'reverse_calc_vat_rate' => 'VAT Rate',
        'reverse_calc_result_net' => 'Net Total',
        'reverse_calc_hint' => 'Calculate the net total from the desired net due',
        'reverse_calc_apply' => 'Apply',
        'reverse_calc_no_lines' => 'Add at least one line before using reverse calculation.',
        'reverse_calc_rounding_notice' => 'The actual net due differs by 1 cent due to fiscal rounding.',

        // Document type
        'document_type' => 'Document Type',

        // Line discount
        'line_discount' => 'Discount %',

        // Payment details section
'payment_details_section' => 'Payment Details',
        'no_payment_details' => 'Payment details not set',
        'payment_terms_label' => 'Payment Terms',
        'payment_method_label' => 'Payment Method',
        'bank_name_label' => 'Bank',
        'bank_iban_label' => 'IBAN',

        // VAT payability and split payment
        'vat_payability_label' => 'VAT Payability',
        'split_payment_label' => 'Split Payment',
        'split_payment_vat_line' => 'VAT (split payment)',

        // Notes / Causale
        'notes_label' => 'Notes / Description',
    ],

    'self_invoices' => [
        'title' => 'Self-Invoices',
        'create_title' => 'New Self-Invoice',
        'edit_title' => 'Self-Invoice #:number',

        'header_section' => 'Header',
        'sequence' => 'Sequence',
        'number' => 'Number',
        'date' => 'Date',
        'supplier' => 'Foreign Supplier',
        'select_supplier' => 'Select a supplier',

        // Related invoice section (DatiFattureCollegate)
        'related_invoice_section' => 'Original Linked Invoice',
        'related_invoice_hint' => 'Enter the details of the original invoice received from the foreign supplier. These fields are mandatory in the SDI standard (DatiFattureCollegate).',
        'document_type' => 'Document Type',
        'related_invoice_number' => 'Original Invoice No.',
        'related_invoice_number_placeholder' => 'e.g. INV-2026-001',
        'related_invoice_date' => 'Original Invoice Date',
        'related_invoice_summary' => 'Linked Invoice:',

        'lines_section' => 'Lines',
        'line_description' => 'Description',
        'line_quantity' => 'Qty',
        'line_unit_of_measure' => 'UoM',
        'line_price' => 'Price',
        'line_vat' => 'VAT',
        'add_line' => 'Add Line',
        'draft_saved' => 'Draft saved at',
        'no_lines' => 'Add the first invoice line.',

        'totals_section' => 'Totals',
        'net_total' => 'Net Total',
        'vat_total' => 'VAT',
        'grand_total' => 'Total',

        'download_xml' => 'Download XML',
        'send_sdi' => 'Send to SDI',

        'status_draft' => 'Draft',
        'status_generated' => 'Generated',
        'status_sent' => 'Sent',

        // Table column headers
        'col_number' => 'Number',
        'col_document_type' => 'Type',
        'col_date' => 'Date',
        'col_supplier' => 'Supplier',
        'col_total' => 'Total',
        'col_status' => 'Status',

        // Summary stats
        'stat_total_invoices' => 'Total self-invoices',
        'stat_total_amount' => 'Total amount',
        'stat_unpaid' => 'Unpaid',
        'stat_overdue' => 'Overdue',

        // Filter labels
        'filter_status' => 'Status',
        'filter_payment' => 'Payment',

        // Toast messages
        'created' => 'Self-invoice created.',
        'updated' => 'Self-invoice updated.',
        'deleted' => 'Self-invoice #:number deleted',
        'filters_cleared' => 'Filters cleared.',
        'readonly_error' => 'This fiscal year is closed. Self-invoices cannot be modified.',
        'cannot_delete_sdi' => 'Cannot delete a self-invoice already processed by SDI.',
        'generation_error' => 'A technical error occurred while generating the file. Please try again or contact support.',
    ],

    'credit_notes' => [
        'title' => 'Credit Notes',
        'create_title' => 'New Credit Note',
        'edit_title' => 'Credit Note #:number',

        'number' => 'Number',
        'date' => 'Date',
        'customer' => 'Customer',
        'select_customer' => 'Select a customer',
        'notes' => 'Notes / Description',

        // Related invoice section (DatiFattureCollegate)
        'related_invoice_section' => 'Original Invoice Reference',
        'related_invoice_hint' => 'Optional link to the original invoice. If filled, it is included in the XML file (DatiFattureCollegate) according to the SDI standard.',
        'related_invoice_number' => 'Original Invoice No.',
        'related_invoice_number_placeholder' => 'e.g. FT-2026-001',
        'related_invoice_date' => 'Original Invoice Date',

        'lines_section' => 'Lines',
        'line_description' => 'Description',
        'line_quantity' => 'Qty',
        'line_unit_of_measure' => 'UoM',
        'line_price' => 'Price',
        'line_vat' => 'VAT',
        'add_line' => 'Add Line',
        'draft_saved' => 'Draft saved at',
        'no_lines' => 'No lines. Add a line to get started.',

        'totals_section' => 'Totals',
        'net_total' => 'Net',
        'vat_total' => 'VAT',
        'grand_total' => 'Total',

        'download_xml' => 'Download XML',
        'send_sdi' => 'Send to SDI',

        // Table column headers
        'col_number' => 'Number',
        'col_date' => 'Date',
        'col_customer' => 'Customer',
        'col_total' => 'Total',
        'col_status' => 'Status',

        // Summary stats
        'stat_total_notes' => 'Total credit notes',
        'stat_total_amount' => 'Total amount',

        // Filter labels
        'filter_status' => 'Status',

        // Toast messages
        'created' => 'Credit note created.',
        'updated' => 'Credit note updated.',
        'deleted' => 'Credit note #:number deleted',
        'filters_cleared' => 'Filters cleared.',
        'readonly_error' => 'This fiscal year is closed. Credit notes cannot be modified.',
        'cannot_delete_sdi' => 'Cannot delete a credit note already processed by SDI.',
        'generation_error' => 'A technical error occurred while generating the file. Please try again or contact support.',
    ],

    'contacts' => [
        'title' => 'Customers',
        'create_title' => 'Create Customer',

        'main_data' => 'General Information',
        'full_name' => 'Company Name / Full Name',
        'fiscal_data' => 'Tax Information',

        'vat_number' => 'VAT Number',
        'vat_number_hint' => 'For EU customers include country prefix (e.g. DE123456789)',
        'tax_code' => 'Tax Code',
        'tax_code_hint' => 'Italian residents only',
        'sdi_code' => 'SDI Recipient Code',
        'sdi_code_hint' => '7 characters or 0000000 if using PEC',
        'pec' => 'PEC',
        'pec_hint' => 'Certified Email (Italy only)',
        'email' => 'Email',

        'address_section' => 'Address',
        'country' => 'Country',
        'address' => 'Address',
        'postal_code' => 'Postal Code',
        'city' => 'City',
        'province' => 'Province',

        // Table column headers
        'col_name' => 'Name',
        'col_vat_number' => 'VAT Number',
        'col_email' => 'E-mail',
        'col_city' => 'City',

        // Toast messages
        'created' => 'Customer created.',
        'updated' => 'Customer updated.',
        'deleted' => ':name deleted',
        'has_invoices' => 'Cannot delete: this customer has linked invoices.',
        'filters_cleared' => 'Filters cleared.',
    ],

    'sequences' => [
        'title' => 'Sequences',
        'create_modal' => 'Create Sequence',
        'edit_modal' => 'Edit Sequence',

        // Table column headers
        'col_name' => 'Name',
        'col_pattern' => 'Pattern',
        'col_type' => 'Type',

        // Form labels
        'name' => 'Name',
        'type' => 'Type',
        'pattern' => 'Pattern',
        'pattern_hint' => 'Use {SEQ} for the sequential number, {ANNO} for the current year (e.g. FE-{SEQ}-{ANNO})',

        // Type labels
        'type_electronic_invoice' => 'Electronic Invoices',
        'type_purchase' => 'Purchases',
        'type_self_invoice' => 'Self-Invoices',
        'type_proforma' => 'ProForma',
        'type_credit_note' => 'Credit Notes',
        'type_quote' => 'Quotes',

        // Toast messages
        'created' => 'Sequence created.',
        'updated' => 'Sequence updated.',
        'deleted' => 'Sequence :name deleted.',
    ],

    'vat_rates' => [
        'title' => 'VAT Rates',
        'create_modal' => 'Create VAT Rate',
        'edit_modal' => 'Edit VAT Rate',

        // Table column headers
        'col_name' => 'Name',
        'col_percent' => '%',
        'col_description' => 'Description',

        // Form labels
        'name' => 'Name',
        'percent' => 'Percent (%)',
        'description' => 'Description',

        // Toast messages
        'created' => 'VAT Rate created.',
        'updated' => 'VAT Rate updated.',
        'deleted' => 'VAT Rate :name deleted.',
    ],

    'settings' => [

        'company' => [
            'title' => 'Company Settings',
            'readonly_title' => 'Read-Only',
            'readonly_description' => 'Company settings cannot be modified in this environment.',

            'general_info' => 'General Information',
            'company_name' => 'Company Name',
            'vat_number' => 'VAT Number',
            'tax_code' => 'Tax Code',

            'address_section' => 'Address',
            'address' => 'Address',
            'postal_code' => 'Postal Code',
            'city' => 'City',
            'province' => 'Province',
            'country' => 'Country',

            'ateco_section' => 'ATECO Codes',
            'ateco_search_placeholder' => 'Search by code or description (e.g. 62, software...)',
            'ateco_no_results' => 'No codes found.',

            'electronic_invoicing' => 'Electronic Invoicing',
            'pec' => 'PEC',
            'sdi_code' => 'SDI Code',

            'logo_section' => 'Company Logo',
            'logo_upload' => 'Upload Logo',
            'logo_hint' => 'PNG or JPG, max 512 KB. Recommended: 400x200 px.',
            'logo_preview_alt' => 'Company logo',
            'remove_logo' => 'Remove',
            'logo_removed' => 'Logo removed.',

            'fund_section' => 'Professional Fund',
            'fund_type' => 'Fund Type',
            'fund_none' => 'None',
            'fund_percent' => 'Contribution Rate',

            // Toast messages
            'saved' => 'Company settings saved.',
            'readonly_error' => 'Company settings cannot be modified in this environment.',
        ],

        'invoice' => [
            'title' => 'Invoice Settings',

            'defaults_section' => 'Defaults',
            'default_sequence' => 'Default Sales Sequence',
            'default_vat_rate' => 'Default VAT Rate',

            'withholding_section' => 'Withholding Tax',
            'withholding_tax_enabled' => 'Enable Withholding Tax by Default',
            'withholding_tax_percent' => 'Default Rate (%)',

            'fund_section' => 'Professional Fund',
            'fund_enabled' => 'Enable Fund by Default on New Invoices',
            'fund_vat_rate' => 'VAT Rate on Contribution',
            'fund_has_deduction' => 'Contribution Subject to Withholding Tax',

            'stamp_duty_section' => 'Stamp Duty (Bollo)',
            'auto_stamp_duty' => 'Auto Apply Stamp Duty',
            'stamp_duty_threshold' => 'Threshold (€)',

            'payments_section' => 'Payments',
            'default_payment_method' => 'Default Payment Method',
            'default_payment_terms' => 'Default Payment Terms',
            'default_bank_name' => 'Default Bank Name',
            'default_iban' => 'Default IBAN',

            'vat_section' => 'VAT',
            'default_vat_payability' => 'Default VAT Payability',
            'default_split_payment' => 'Split Payment by Default',

            'other_section' => 'Other',
            'default_notes' => 'Default Notes / Description',

            // Toast messages
            'saved' => 'Invoice settings saved.',
        ],

        'email' => [
            'title' => 'Email Settings',
            'readonly_title' => 'Read-only',
            'readonly_description' => 'Email settings cannot be edited in this environment.',
            'readonly_error' => 'Email settings cannot be edited in this environment.',

            'smtp_section' => 'SMTP Configuration',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'Port',
            'smtp_username' => 'Username',
            'smtp_password' => 'Password',
            'smtp_encryption' => 'Encryption',
            'encryption_none' => 'None',

            'sender_section' => 'Sender',
            'from_address' => 'Sender address',
            'from_name' => 'Sender name',

            'template_sales' => 'Invoice Template',
            'template_proforma' => 'Proforma Template',
            'template_subject' => 'Subject',
            'template_body' => 'Message body',
            'auto_send' => 'Auto-send',

            'smtp_managed_by_env_title' => 'SMTP managed by the platform',
            'smtp_managed_by_env_description' => 'In this environment, SMTP configuration is managed automatically by the hosting infrastructure.',

            // Toast messages
            'saved' => 'Email settings saved.',
        ],

        'services' => [
            'title' => 'Services',
            'readonly_title' => 'Read-only',
            'readonly_description' => 'Service settings cannot be modified in this environment.',
            'readonly_error' => 'Service settings cannot be modified in this environment.',

            'backup' => [
                'title' => 'Backup',
                'subtitle' => 'Automatic backup of the database and documents to S3 storage.',
                'managed_by_env_title' => 'Backup managed by the platform',
                'managed_by_env_description' => 'In this environment, backups are managed automatically by the hosting infrastructure.',
                'enabled' => 'Enable automatic backup',

                'schedule_section' => 'Schedule',
                'frequency' => 'Frequency',
                'frequency_daily' => 'Daily',
                'frequency_weekly' => 'Weekly',
                'frequency_monthly' => 'Monthly',
                'time' => 'Time',
                'day_of_week' => 'Day of week',
                'day_of_month' => 'Day of month',

                's3_section' => 'S3 Destination',
                'aws_access_key_id' => 'Access Key ID',
                'aws_secret_access_key' => 'Secret Access Key',
                'aws_default_region' => 'Region',
                'aws_bucket' => 'Bucket',
                'aws_endpoint' => 'Custom endpoint',
                'aws_endpoint_hint' => 'Leave empty for AWS S3. Fill in for compatible providers (MinIO, Backblaze, DigitalOcean Spaces).',
                'aws_use_path_style_endpoint' => 'Use path-style URL',
                'aws_use_path_style_endpoint_hint' => 'Required for some S3-compatible providers such as MinIO.',

                'test_connection' => 'Test connection',
                'test_connection_hint' => 'Save your credentials first, then verify the connection.',
                'connection_success' => 'S3 connection successful.',
                'connection_error' => 'Unable to connect to the S3 bucket. Check your credentials.',

                'saved' => 'Backup settings saved.',
            ],
        ],

        'sdi' => [
            'no_provider_title' => 'No SDI provider installed',
            'no_provider_description' => 'Install a provider plugin (e.g., OpenAPI) to enable electronic invoicing.',
        ],

        'plugins' => [
            'title' => 'Plugins',
            'subtitle' => 'Installed and active plugins',
            'empty' => 'No plugins installed.',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'author' => 'Author',
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
            'locked' => 'Locked',
            'activated' => 'Plugin activated. Reload the page to apply changes.',
            'deactivated' => 'Plugin deactivated. Reload the page to apply changes.',
            'deactivate_confirm' => 'Deactivate :name? Its features will be unavailable until reactivated.',
            'restart_hint' => 'Activation changes take effect on next page load.',
        ],

    ],

    'setup' => [
        'subtitle' => 'Initial configuration',
        'step_account' => 'Account',
        'step_company' => 'Company',
        'step_address' => 'Address',
        'account_name' => 'Name',
        'account_email' => 'Email',
        'account_password' => 'Password',
        'account_password_confirm' => 'Confirm Password',
        'fiscal_regime' => 'Fiscal Regime',
        'invoice_defaults' => 'Invoice Defaults',
        'auto_stamp_duty' => 'Automatic Stamp Duty',
        'auto_stamp_duty_hint' => 'Automatically apply €2 stamp duty on invoices above €77.47 without VAT',
        'withholding_tax_enabled' => 'Withholding Tax',
        'withholding_tax_hint' => 'Enable withholding tax (ritenuta d\'acconto) on invoices by default',
        'next' => 'Next',
        'back' => 'Back',
        'complete' => 'Complete Setup',
        'step_account_desc' => 'Create your admin account',
        'step_company_desc' => 'Enter your company details',
        'step_address_desc' => 'Last step: address and e-invoicing settings',
    ],

    'landing' => [
        'tagline' => 'Italian electronic invoicing, simple and fast',
        'feature_xml_title' => 'Compliant XML',
        'feature_xml_desc' => 'Generate electronic invoices in the standard Fattura PA/B2B format',
        'feature_sdi_title' => 'Direct SDI submission',
        'feature_sdi_desc' => 'Submit invoices to the Sistema di Interscambio with one click',
        'feature_dashboard_title' => 'Dashboard & reports',
        'feature_dashboard_desc' => 'Monitor revenue, VAT and invoice status in real time',
        'trust_line' => 'Used by professionals and small businesses across Italy',
    ],

    'auth' => [
        'email' => 'Email',
        'password' => 'Password',
        'remember_me' => 'Remember me',
        'login' => 'Log in',
        'welcome_back' => 'Welcome back',
        'login_subtitle' => 'Sign in to your account to continue',
        'no_account' => 'Don\'t have an account?',
        'setup_link' => 'Set up Fatturino',
    ],

    'imports' => [
        'title' => 'Imports',

        'xml_section' => 'Fattura Elettronica XML',
        'xml_section_desc' => 'Import invoices from the Italian standard XML format',

        'platforms_section' => 'Platforms',
        'platforms_section_desc' => 'Import data from third-party invoicing software',

        'xml_sales_title' => 'XML Sales Invoices',
        'xml_sales_desc' => 'Import active invoices from Fattura Elettronica XML files (.xml, .p7m)',

        'xml_purchase_title' => 'XML Purchase Invoices',
        'xml_purchase_desc' => 'Import passive invoices from Fattura Elettronica XML files (.xml, .p7m)',

        'xml_self_invoice_title' => 'XML Self-Invoices',
        'xml_self_invoice_desc' => 'Import self-invoices (TD17, TD18, TD19, TD28) from Fattura Elettronica XML files (.xml, .p7m)',

        'fattura24_contacts_title' => 'Fattura24 — Contacts',
        'fattura24_contacts_desc' => 'Import customers and suppliers from a Fattura24 CSV address book export',
        'aruba_contacts_title' => 'Aruba — Contacts',
        'aruba_contacts_desc' => 'Import customers and suppliers from Aruba Fatturazione address book',
        'fic_contacts_title' => 'Fatture in Cloud — Contacts',
        'fic_contacts_desc' => 'Import customers and suppliers from Fatture in Cloud address book',

        'start_import' => 'Import',
        'run_import' => 'Start Import',
        'import_another' => 'Import Another',

        'xml_file_label' => 'Fattura Elettronica File',
        'xml_file_hint' => 'Supported formats: .xml, .p7m (with digital signature), .zip (archive with multiple XML files)',
        'csv_file_label' => 'Fattura24 CSV File',
        'csv_file_hint' => 'Export the address book from Fattura24 as a CSV file',
        'select_sequence' => 'Sequence',
        'select_sequence_placeholder' => 'Select a sequence...',
        'update_existing' => 'Update existing contacts',
        'update_existing_hint' => 'If enabled, updates existing contacts with the same VAT number',

        'completed_no_errors' => 'Import completed without errors.',
        'completed_with_errors' => 'Import completed with some errors.',

        'stat_invoices_imported' => 'Invoices imported',
        'stat_contacts_created' => 'Contacts created',
        'stat_total' => 'Total rows',
        'stat_imported' => 'Imported',
        'stat_updated' => 'Updated',
        'stat_skipped' => 'Skipped',
        'stat_errors' => 'Errors',

        'error_details' => 'Error details',
        'no_sequence_available' => 'No sequence available for this category. Configure one in Settings > Sequences.',
        'zip_open_error' => 'Unable to open the ZIP file.',
        'zip_no_xml' => 'The ZIP archive contains no valid XML or P7M files.',
    ],

    'wip' => [
        'title' => 'Work in Progress',
        'message' => 'This page is not ready yet',
        'back_to_dashboard' => 'Back to Dashboard',
    ],

    'email' => [
        // Modal and actions
        'send_email' => 'Send Email',
        'send' => 'Send',
        'recipient' => 'Recipient',
        'cc' => 'CC (Copy)',
        'subject' => 'Subject',
        'body' => 'Message',
        'confirm_send' => 'Send email to the customer?',
        'log_title' => 'Email History',

        // Status enum labels
        'status_queued' => 'Queued',
        'status_sent' => 'Sent',
        'status_failed' => 'Failed',

        // Toast messages
        'sent_success' => 'Email sent successfully.',
        'send_error' => 'Email send error: :error',
        'send_not_allowed' => 'Email sending is not available in demo mode.',
        'no_recipient' => 'No email address available for this contact.',
        'test_connection' => 'Test Connection',
        'test_success' => 'Test email sent successfully.',
        'test_error' => 'SMTP connection error:',
        'test_error_no_recipient' => 'No sender address configured. Set the "From address" field before testing.',

        // Test email content
        'test_subject' => 'SMTP connection test - Fatturino',
        'test_body' => 'This is a test message sent from Fatturino to verify the SMTP configuration.',

        // Helper text
        'test_connection_hint' => 'Sends a test email to the sender address to verify the connection.',
        'placeholders_hint' => 'Available variables: {CLIENTE}, {NUMERO_DOCUMENTO}, {DATA_DOCUMENTO}, {IMPORTO_NETTO}, {IMPORTO_IVA}, {IMPORTO_TOTALE}, {AZIENDA}, {PARTITA_IVA_AZIENDA}, {EMAIL_CLIENTE}',
        'attach_pdf' => 'Attach courtesy PDF',
    ],

    'errors' => [
        '404_title' => 'Page not found',
        '404_desc' => 'The page you are looking for does not exist or has been moved.',
        '500_title' => 'Server error',
        '500_desc' => 'Something went wrong. Please try again in a moment.',
        'go_home' => 'Go Home',
        'go_back' => 'Go Back',
        'retry' => 'Try Again',
    ],

    'pdf' => [
        'courtesy_title' => 'COURTESY INVOICE',
        'proforma_title' => 'PROFORMA INVOICE',
        'credit_note_title' => 'CREDIT NOTE',

        'supplier_section' => 'Supplier',
        'customer_section' => 'Bill To',

        'invoice_number' => 'Invoice No.',
        'invoice_date' => 'Date',
        'due_date' => 'Due Date',
        'document_type' => 'Document Type',

        'line_description' => 'Description',
        'line_quantity' => 'Qty',
        'line_unit' => 'Unit',
        'line_unit_price' => 'Unit Price',
        'line_discount' => 'Discount',
        'line_vat' => 'VAT',
        'line_amount' => 'Amount',

        'vat_summary' => 'VAT Summary',
        'vat_rate' => 'Rate',
        'taxable' => 'Taxable',
        'vat_amount' => 'Tax',

        'payment_info' => 'Payment',
        'payment_method' => 'Method',
        'bank' => 'Bank',
        'iban' => 'IBAN',

        'net_total' => 'Net Total',
        'fund_contribution' => 'Professional Fund (:percent%)',
        'vat_total' => 'VAT',
        'gross_total' => 'Total',
        'stamp_duty' => 'Stamp Duty',
        'withholding_tax' => 'Withholding Tax (:percent%)',
        'split_payment_deduction' => 'Split Payment (VAT)',
        'net_due' => 'TOTAL DUE',

        'notes' => 'Notes',

        'sdi_disclaimer' => 'This document has no fiscal value pursuant to art. 21 of Presidential Decree 633/72. The original invoice was sent electronically via the Sistema di Interscambio (SDI).',
    ],

    'payments' => [
        'title' => 'Payments',
        'registered_payments' => 'Registered payments',
        'no_payments' => 'No payments recorded.',
        'remaining_balance' => 'Remaining balance',
        'add_payment' => 'Record payment',
        'record_payment' => 'Record',
        'mark_as_paid' => 'Mark as paid',
        'amount' => 'Amount',
        'date' => 'Date',
        'method' => 'Method',
        'method_optional' => 'Method (optional)',
        'reference' => 'Reference',
        'reference_placeholder' => 'Bank reference, check no., TRN...',
        'notes' => 'Notes',
        'delete_confirm' => 'Delete this payment?',
        'delete_yes' => 'Delete',
        'payment_added' => 'Payment recorded.',
        'payment_deleted' => 'Payment deleted.',
    ],

    'days' => [
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ],

    'conservation' => [
        // Setup wizard section
        'section_title' => 'Legal long-term storage',
        'setup_description' => 'Fatturino does not provide legally compliant invoice archival. You must enrol (free of charge) in the Italian Revenue Agency storage service from your Fatture e Corrispettivi area.',
        'setup_acknowledge_label' => 'I confirm I have enrolled (or will enrol shortly) in the free legal storage service offered by the Italian Revenue Agency',
        'setup_acknowledge_hint' => 'Without enrolment, transmitted invoices are not archived in a legally compliant way.',

        // Banner on Electronic Invoicing page
        'banner_title' => 'Legal storage not yet confirmed',
        'banner_description' => 'Fatturino does not archive invoices in a legally compliant way. Enrol in the free service offered by the Italian Revenue Agency through Fatture e Corrispettivi, then confirm below.',
        'link_label' => 'Open AdE service',
        'acknowledge_button' => 'Confirm enrolment',

        // Confirmation state
        'acknowledged_title' => 'Legal storage: enrolment confirmed',
        'acknowledged_description' => 'You have declared enrolment in the Italian Revenue Agency storage service. Long-term storage is fully handled by AdE.',
        'acknowledged_toast' => 'AdE legal storage enrolment recorded.',
    ],
];
