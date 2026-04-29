<?php

return [
    'common' => [
        'search' => 'Cerca...',
        'filters' => 'Filtri',
        'create' => 'Crea',
        'reset' => 'Azzera',
        'done' => 'Fatto',
        'cancel' => 'Annulla',
        'save' => 'Salva',
        'confirm_delete' => 'Sei sicuro?',
        'confirm_title' => 'Conferma',
        'confirm' => 'Conferma',
        'filters_cleared' => 'Filtri azzerati.',
        'logoff' => 'Disconnetti',
        'goodbye' => 'Arrivederci!',
        'unknown' => 'Sconosciuto',
        'delete' => 'Elimina',
        'collapse' => 'Comprimi',
        'select' => 'Seleziona...',
        'all' => 'Tutti',
        'empty_table' => 'Ancora niente qui.',
        'close' => 'Chiudi',
    ],

    'nav' => [
        'dashboard' => 'Dashboard',
        'sales' => 'Vendite',
        'sell_invoices' => 'Fatture',
        'credit_notes' => 'Note di Credito',
        'proforma' => 'Proforma',
        'purchases' => 'Acquisti',
        'purchase_invoices' => 'Fatture',
        'self_invoices' => 'Autofatture',
        'imports' => 'Importazioni',
        'contacts' => 'Clienti & Fornitori',
        'products' => 'Prodotti',
        'configuration' => 'Configurazione',
        'sequences' => 'Sequenze',
        'vat_rates' => 'Aliquote IVA',
        'company_settings' => 'Impostazioni Azienda',
        'invoice_settings' => 'Impostazioni Fattura',
        'electronic_invoice_settings' => 'Impostazioni FE',
        'email_settings' => 'Impostazioni Email',
        'services' => 'Servizi',
        'plugins' => 'Plugin',
        'audit_log' => 'Registro Attività',
    ],

    'audit' => [
        'timeline_empty' => 'Nessuna attività registrata.',
        'grouped_line_changes' => ':count modifiche alle righe',
        'expand' => 'Espandi',
        'collapse' => 'Comprimi',
        'system' => 'Sistema',
        'tab_details' => 'Dettagli',
        'tab_history' => 'Cronologia',
        'events' => [
            'created' => 'Documento creato',
            'updated' => 'Documento aggiornato',
            'deleted' => 'Documento eliminato',
            'restored' => 'Documento ripristinato',
            'email_sent' => 'Email inviata',
            'sdi_sent' => 'Inviata a SDI',
            'sdi_accepted' => 'Accettata da SDI',
            'sdi_rejected' => 'Rifiutata da SDI',
            'sdi_sent_event' => 'Invio a SDI',
            'sdi_error' => 'Errore SDI',
        ],
        'index' => [
            'title' => 'Registro Attività',
            'filter_user' => 'Utente',
            'filter_event' => 'Evento',
            'filter_entity' => 'Entità',
            'filter_from' => 'Da',
            'filter_to' => 'A',
            'column_date' => 'Data',
            'column_user' => 'Utente',
            'column_event' => 'Evento',
            'column_entity' => 'Entità',
            'column_actions' => 'Azioni',
            'details' => 'Dettagli',
            'no_changes' => 'Nessuna modifica registrata.',
            'old_value' => 'Precedente',
            'new_value' => 'Nuovo',
        ],
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'recent_invoices' => 'Fatture Recenti',
        'welcome' => 'Benvenuto nella tua dashboard!',

        // KPI stat cards
        'stat_revenue_month' => 'Fatturato (mese)',
        'stat_revenue_ytd' => 'Fatturato (anno)',
        'stat_revenue_dec' => 'Fatturato (Dic. :year)',
        'stat_revenue_year' => 'Fatturato :year',
        'stat_invoices_month' => 'Fatture emesse (mese)',
        'stat_active_clients' => 'Clienti attivi (anno)',
        'vs_last_month' => 'vs mese scorso',
        'vs_nov' => 'vs Nov. :year',
        'invoices_issued' => 'fatture emesse',
        'avg_invoice' => 'Media:',
        'total_contacts' => 'contatti totali',

        // Top clients section
        'top_clients_title' => 'Top Clienti (anno)',
        'no_data' => 'Nessun dato disponibile',

        // Fiscal summary
        'vat_collected_ytd' => 'IVA Riscossa (anno)',
        'withholding_ytd' => 'Ritenuta d\'acconto (anno)',
        'year_to_date' => 'anno in corso',
        'full_year' => 'anno :year completo',

        // Read-only banner for past fiscal years
        'readonly_year_title' => 'Anno fiscale :year, sola visualizzazione',

        // Recent invoices
        'view_all' => 'Vedi tutte',
        'no_invoices' => 'Nessuna fattura ancora',

        // Payment widget
        'payments_title' => 'Stato Pagamenti',
        'payments_unpaid' => 'Da incassare',
        'payments_partial' => 'Parziali',
        'payments_paid' => 'Incassate',
        'payments_overdue' => 'scadute',
        'payments_upcoming' => 'Prossime scadenze',

        // VAT Balance widget
        'vat_balance_title' => 'Saldo IVA',
        'vat_collected_label' => 'Riscossa (vendite)',
        'vat_on_purchases_label' => 'Su acquisti',
        'vat_balance_label' => 'Saldo',
        'vat_balance_owed' => 'da versare',
        'vat_balance_credit' => 'a credito',

        // Cashflow widget
        'cashflow_title' => 'Cashflow Previsionale',
        'cashflow_inflows' => 'Entrate',
        'cashflow_outflows' => 'Uscite',
        'cashflow_net' => 'Netto',
        'overdue' => 'Scaduto',
        'no_cashflow_data' => 'Nessuna fattura con scadenza nei prossimi 6 mesi.',
    ],

    'purchase_invoices' => [
        'title' => 'Acquisti',
        'create_title' => 'Registra Acquisto',
        'edit_title' => 'Modifica Acquisto #:number',

        'header_section' => 'Testata',
        'sequence' => 'Sezionale',
        'number' => 'Numero',
        'date' => 'Data',
        'supplier' => 'Fornitore',
        'select_supplier' => 'Seleziona un fornitore',

        'lines_section' => 'Righe',
        'line_description' => 'Descrizione',
        'line_quantity' => 'Q.tà',
        'line_unit_of_measure' => 'UM',
        'line_price' => 'Prezzo',
        'line_vat' => 'IVA',
        'add_line' => 'Aggiungi Riga',
        'no_lines' => 'Nessuna riga. Aggiungi una riga per iniziare.',

        'totals_section' => 'Totali',
        'net_total' => 'Imponibile',
        'vat_total' => 'IVA',
        'grand_total' => 'Totale',

        'status_draft' => 'Bozza',
        'status_generated' => 'Registrata',
        'status_sent' => 'Contabilizzata',
        'status_received' => 'Ricevuta da SDI',

        // Listing stats
        'stat_total_invoices' => 'Acquisti',
        'stat_total_amount' => 'Totale spese',
        'stat_unpaid' => 'Non pagati',
        'stat_overdue' => 'Scaduti',
        'filter_status' => 'Stato fattura',
        'filter_payment' => 'Stato pagamento',

        // Table column headers
        'col_number' => 'Numero',
        'col_date' => 'Data',
        'col_supplier' => 'Fornitore',
        'col_total' => 'Totale',
        'col_status' => 'Stato',

        // Toast messages
        'created' => 'Acquisto registrato.',
        'updated' => 'Acquisto aggiornato.',
        'deleted' => 'Acquisto #:number eliminato',
        'cannot_delete_sdi' => 'Non è possibile eliminare una fattura ricevuta dallo SDI.',
        'filters_cleared' => 'Filtri azzerati.',
        'readonly_error' => 'Questo anno fiscale è concluso. Non è possibile modificare gli acquisti.',
        'readonly_banner' => 'Anno fiscale :year concluso, sola visualizzazione',
        'sdi_received_banner' => 'Fattura ricevuta dallo SDI, sola visualizzazione',
        'import_only_alert' => 'Le fatture di acquisto vengono importate automaticamente tramite la funzionalità di importazione o il webhook di Fatturazione Elettronica. Non è possibile crearle manualmente.',
    ],

    'invoices' => [
        'title' => 'Fatture',
        'create_title' => 'Crea Fattura',
        'edit_title' => 'Modifica Fattura #:number',

        'header_section' => 'Testata',
        'sequence' => 'Sezionale',
        'number' => 'Numero',
        'date' => 'Data',
        'customer' => 'Cliente',
        'select_customer' => 'Seleziona un cliente',

        'lines_section' => 'Righe',
        'line_description' => 'Descrizione',
        'line_quantity' => 'Q.tà',
        'line_unit_of_measure' => 'UM',
        'line_price' => 'Prezzo',
        'line_vat' => 'IVA',
        'add_line' => 'Aggiungi Riga',
        'no_lines' => 'Nessuna riga. Aggiungi una riga per iniziare.',

        'tax_options_section' => 'Opzioni Fiscali',
        'totals_section' => 'Totali',
        'net_total' => 'Imponibile',
        'vat_total' => 'IVA',
        'grand_total' => 'Totale',

        // Withholding tax (Ritenuta d'acconto)
        'withholding_tax_label' => 'Soggetto a Ritenuta d\'Acconto',
        'withholding_tax_percent_label' => 'Percentuale Ritenuta',
        'withholding_tax_amount_label' => "Ritenuta d'Acconto (:percent%)",
        'net_due' => 'Netto a Pagare',

        // Professional fund (Cassa Previdenziale)
        'fund_label' => 'Cassa Previdenziale',
        'fund_type_label' => 'Tipo Cassa',
        'fund_percent_label' => 'Aliquota Rivalsa',
        'fund_vat_rate_label' => 'Aliquota IVA Rivalsa',
        'fund_amount_label' => 'Rivalsa Previdenziale (:percent%)',

        // Stamp duty (Marca da bollo)
        'stamp_duty_label' => 'Bollo Virtuale (€ 2,00)',
        'stamp_duty_hint' => 'Marca da bollo di € 2,00 applicata per importi superiori alla soglia',

        'download_pdf' => 'Scarica PDF',
        'pdf_generation_error' => 'Errore generazione PDF: :error',
        'download_xml' => 'Scarica XML',
        'send_to_sdi' => 'Invia a SDI',
        'sent_to_sdi' => 'Inviata a SDI',

        'status_draft' => 'Bozza',
        'status_generated' => 'Generata',
        'status_sent' => 'Inviata',

        // Listing stats
        'stat_total_invoices' => 'Fatture',
        'stat_total_amount' => 'Fatturato',
        'stat_unpaid' => 'Non pagate',
        'stat_overdue' => 'Scadute',
        'filter_status' => 'Stato fattura',
        'filter_payment' => 'Stato pagamento',

        // Table column headers
        'col_number' => 'Numero',
        'col_date' => 'Data',
        'col_customer' => 'Cliente',
        'col_total' => 'Totale',
        'col_status' => 'Stato',

        // Toast messages
        'created' => 'Fattura creata.',
        'updated' => 'Fattura aggiornata.',
        'deleted' => 'Fattura #:number eliminata',
        'filters_cleared' => 'Filtri azzerati.',
        'save_before_send' => 'Salva le modifiche prima di inviare.',
        'openapi_not_configured' => 'OpenAPI SDI non configurato. Vai in Impostazioni > OpenAPI.',
        'xml_invalid' => 'XML non valido: :errors',
        'confirm_send_sdi' => 'Confermi l\'invio della fattura allo SDI?',
        'sent_success' => 'Fattura inviata allo SDI con successo!',
        'send_error' => 'Errore invio SDI: :error',
        'generation_error' => 'Errore durante la generazione/invio: :error',
        'readonly_error' => 'Questo anno fiscale è concluso. Non è possibile modificare le fatture.',
        'readonly_banner' => 'Anno fiscale :year concluso, sola visualizzazione',
        'sdi_locked_banner' => 'Fattura inviata allo SDI, non modificabile',
        'sdi_not_configured_hint' => 'Attiva la Fatturazione Elettronica nelle impostazioni per validare e inviare allo SDI.',

        // InvoiceStatus enum labels
        'invoice_status_draft' => 'Bozza',
        'invoice_status_generated' => 'Registrata',
        'invoice_status_xml_validated' => 'XML Validato',
        'invoice_status_sent' => 'Inviata',

        // PaymentStatus enum labels
        'payment_status_unpaid' => 'Non pagata',
        'payment_status_partial' => 'Parziale',
        'payment_status_paid' => 'Pagata',
        'payment_status_overdue' => 'Scaduta',

        // Payment section
        'col_payment' => 'Pagamento',
        'payment_section' => 'Pagamento',
        'due_date' => 'Data Scadenza',
        'paid_at' => 'Data Pagamento',
        'paid_amount' => 'Importo Pagato',
        'save_payment' => 'Salva Pagamento',
        'payment_saved' => 'Pagamento salvato.',

        // XML validation
        'validate_xml' => 'Valida XML',
        'xml_validated_success' => 'XML validato con successo.',
        'confirm_validate_xml' => 'Confermi la validazione dell\'XML?',

        // XML validation error messages
        'xml_errors' => [
            'missing_header' => 'Intestazione fattura elettronica mancante',
            'missing_body' => 'Corpo fattura elettronica mancante',
            'missing_sender_id' => 'Identificativo trasmittente mancante',
            'missing_progressive' => 'Numero progressivo invio mancante',
            'missing_format' => 'Formato trasmissione mancante',
            'missing_recipient' => 'Codice destinatario e PEC mancanti: inserire almeno uno dei due',
            'missing_transmission_data' => 'Dati di trasmissione mancanti',
            'parsing_error' => 'Errore di lettura XML: :error',
            'missing_customer_fiscal_id' => 'Il cliente non ha Partita IVA né Codice Fiscale valido (11-16 caratteri): aggiornare l\'anagrafica prima di inviare.',
        ],
        'cannot_send_not_validated' => 'L\'XML deve essere validato prima di poter inviare allo SDI.',

        // SDI status labels
        'sdi_sent' => 'Inviata',
        'sdi_rejected' => 'Scartata',
        'sdi_delivered' => 'Consegnata',
        'sdi_not_delivered' => 'Mancata Consegna',
        'sdi_expired' => 'Decorrenza Termini',
        'sdi_accepted' => 'Accettata',
        'sdi_refused' => 'Rifiutata',
        'sdi_error' => 'Errore',
        'sdi_received' => 'Ricevuta da SDI',

        // SDI log
        'sdi_log_title' => 'Registro SDI',
        'sdi_log_received_by_sdi' => 'Fattura ricevuta dallo SDI',

        // Reverse calculation modal (Scorporo)
        'reverse_calc_title' => 'Scorporo Totale',
        'reverse_calc_desired_net' => 'Netto a Pagare Desiderato',
        'reverse_calc_vat_rate' => 'Aliquota IVA',
        'reverse_calc_result_net' => 'Imponibile',
        'reverse_calc_hint' => 'Calcola l\'imponibile partendo dal netto che vuoi incassare',
        'reverse_calc_apply' => 'Applica',
        'reverse_calc_no_lines' => 'Aggiungi almeno una riga prima di usare lo scorporo.',
        'reverse_calc_rounding_notice' => 'Il netto effettivo differisce di 1 centesimo per via degli arrotondamenti fiscali.',

        // Document type
        'document_type' => 'Tipo Documento',

        // Line discount
        'line_discount' => 'Sconto %',

        // Payment details section
        'payment_details_section' => 'Dati Pagamento',
        'payment_terms_label' => 'Termini di Pagamento',
        'payment_method_label' => 'Metodo di Pagamento',
        'bank_name_label' => 'Banca',
        'bank_iban_label' => 'IBAN',

        // VAT payability and split payment
        'vat_payability_label' => 'Esigibilita IVA',
        'split_payment_label' => 'Split Payment (Scissione)',
        'split_payment_vat_line' => 'IVA (split payment)',

        // Notes / Causale
        'notes_label' => 'Note / Causale',
    ],

    'proforma' => [
        'title' => 'Proforma',
        'create_title' => 'Nuova Proforma',
        'edit_title' => 'Proforma #:number',

        'header_section' => 'Testata',
        'sequence' => 'Sezionale',
        'number' => 'Numero',
        'date' => 'Data',
        'customer' => 'Cliente',
        'select_customer' => 'Seleziona un cliente',

        'lines_section' => 'Righe',
        'line_description' => 'Descrizione',
        'line_quantity' => 'Q.tà',
        'line_unit_of_measure' => 'UM',
        'line_price' => 'Prezzo',
        'line_vat' => 'IVA',
        'add_line' => 'Aggiungi Riga',
        'no_lines' => 'Nessuna riga. Aggiungi una riga per iniziare.',

        'tax_options_section' => 'Opzioni Fiscali',
        'totals_section' => 'Totali',
        'net_total' => 'Imponibile',
        'vat_total' => 'IVA',
        'grand_total' => 'Totale',

        // Withholding tax
        'withholding_tax_label' => 'Soggetto a Ritenuta d\'Acconto',
        'withholding_tax_percent_label' => 'Percentuale Ritenuta',
        'withholding_tax_amount_label' => "Ritenuta d\'Acconto (:percent%)",
        'net_due' => 'Netto a Pagare',

        // Professional fund
        'fund_label' => 'Cassa Previdenziale',
        'fund_type_label' => 'Tipo Cassa',
        'fund_percent_label' => 'Aliquota Rivalsa',
        'fund_vat_rate_label' => 'Aliquota IVA Rivalsa',
        'fund_amount_label' => 'Rivalsa Previdenziale (:percent%)',

        // Stamp duty
        'stamp_duty_label' => 'Bollo Virtuale (€ 2,00)',
        'stamp_duty_hint' => 'Marca da bollo di € 2,00 applicata per importi superiori alla soglia',

        // ProformaStatus enum labels
        'status_draft' => 'Bozza',
        'status_sent' => 'Inviata',
        'status_converted' => 'Convertita',
        'status_cancelled' => 'Annullata',

        // Stats
        'stat_total' => 'Proforma',
        'stat_total_amount' => 'Importo Totale',
        'stat_unpaid' => 'Non pagate',
        'stat_converted' => 'Convertite',
        'filter_status' => 'Stato proforma',
        'filter_payment' => 'Stato pagamento',

        // Table columns
        'col_number' => 'Numero',
        'col_date' => 'Data',
        'col_customer' => 'Cliente',
        'col_total' => 'Totale',
        'col_status' => 'Stato',
        'col_payment' => 'Pagamento',

        // Actions
        'mark_as_sent' => 'Segna come Inviata',
        'convert_to_invoice' => 'Converti in Fattura',
        'cancel_proforma' => 'Annulla Proforma',
        'confirm_convert' => 'Confermi la conversione in fattura elettronica?',
        'confirm_cancel' => 'Confermi l\'annullamento della proforma?',
        'confirm_mark_sent' => 'Confermi di segnare la proforma come inviata?',

        // Toast messages
        'created' => 'Proforma creata.',
        'updated' => 'Proforma aggiornata.',
        'deleted' => 'Proforma #:number eliminata.',
        'marked_sent' => 'Proforma segnata come inviata.',
        'converted_success' => 'Proforma convertita in fattura #:number.',
        'cancelled' => 'Proforma annullata.',
        'already_converted' => 'Questa proforma è già stata convertita.',
        'cannot_convert' => 'Impossibile convertire: la proforma deve essere in stato Bozza o Inviata.',
        'readonly_error' => 'Questo anno fiscale è concluso. Non è possibile modificare le proforma.',
        'readonly_banner' => 'Anno fiscale :year concluso, sola visualizzazione',
        'converted_banner' => 'Proforma convertita in fattura',
        'cancelled_banner' => 'Proforma annullata',
        'filters_cleared' => 'Filtri azzerati.',

        // Payment
        'payment_section' => 'Pagamento',
        'due_date' => 'Data Scadenza',
        'paid_at' => 'Data Pagamento',
        'paid_amount' => 'Importo Pagato',
        'save_payment' => 'Salva Pagamento',
        'payment_saved' => 'Pagamento salvato.',

        // PaymentStatus enum labels
        'payment_status_unpaid' => 'Non pagata',
        'payment_status_partial' => 'Parziale',
        'payment_status_paid' => 'Pagata',
        'payment_status_overdue' => 'Scaduta',

        // Reverse calculation
        'reverse_calc_title' => 'Scorporo Totale',
        'reverse_calc_desired_net' => 'Netto a Pagare Desiderato',
        'reverse_calc_vat_rate' => 'Aliquota IVA',
        'reverse_calc_result_net' => 'Imponibile',
        'reverse_calc_hint' => 'Calcola l\'imponibile partendo dal netto che vuoi incassare',
        'reverse_calc_apply' => 'Applica',
        'reverse_calc_no_lines' => 'Aggiungi almeno una riga prima di usare lo scorporo.',
        'reverse_calc_rounding_notice' => 'Il netto effettivo differisce di 1 centesimo per via degli arrotondamenti fiscali.',
    ],

    'self_invoices' => [
        'title' => 'Autofatture',
        'create_title' => 'Nuova Autofattura',
        'edit_title' => 'Autofattura #:number',

        'header_section' => 'Testata',
        'sequence' => 'Sezionale',
        'number' => 'Numero',
        'date' => 'Data',
        'supplier' => 'Fornitore Estero',
        'select_supplier' => 'Seleziona un fornitore',

        // Related invoice section (DatiFattureCollegate)
        'related_invoice_section' => 'Fattura Originale Collegata',
        'related_invoice_hint' => 'Inserisci i dati della fattura originale ricevuta dal fornitore estero. Questi dati sono obbligatori nello standard SDI (DatiFattureCollegate).',
        'document_type' => 'Tipo Documento',
        'related_invoice_number' => 'N° Fattura Originale',
        'related_invoice_number_placeholder' => 'Es. INV-2026-001',
        'related_invoice_date' => 'Data Fattura Originale',
        'related_invoice_summary' => 'Fattura Collegata:',

        'lines_section' => 'Righe',
        'line_description' => 'Descrizione',
        'line_quantity' => 'Q.tà',
        'line_unit_of_measure' => 'UM',
        'line_price' => 'Prezzo',
        'line_vat' => 'IVA',
        'add_line' => 'Aggiungi Riga',
        'no_lines' => 'Nessuna riga. Aggiungi una riga per iniziare.',

        'totals_section' => 'Totali',
        'net_total' => 'Imponibile',
        'vat_total' => 'IVA',
        'grand_total' => 'Totale',

        'download_xml' => 'Scarica XML',
        'send_sdi' => 'Invia a SDI',

        'status_draft' => 'Bozza',
        'status_generated' => 'Generata',
        'status_sent' => 'Inviata',

        // Table column headers
        'col_number' => 'Numero',
        'col_document_type' => 'Tipo',
        'col_date' => 'Data',
        'col_supplier' => 'Fornitore',
        'col_total' => 'Totale',
        'col_status' => 'Stato',

        // Summary stats
        'stat_total_invoices' => 'Autofatture totali',
        'stat_total_amount' => 'Totale importo',
        'stat_unpaid' => 'Non pagate',
        'stat_overdue' => 'Scadute',

        // Filter labels
        'filter_status' => 'Stato',
        'filter_payment' => 'Pagamento',

        // Toast messages
        'created' => 'Autofattura creata.',
        'updated' => 'Autofattura aggiornata.',
        'deleted' => 'Autofattura #:number eliminata',
        'filters_cleared' => 'Filtri azzerati.',
        'readonly_error' => 'Questo anno fiscale è concluso. Non è possibile modificare le autofatture.',
        'cannot_delete_sdi' => 'Non è possibile eliminare un\'autofattura già processata dallo SDI.',
        'generation_error' => 'Errore durante la generazione/invio: :error',
    ],

    'credit_notes' => [
        'title' => 'Note di Credito',
        'create_title' => 'Nuova Nota di Credito',
        'edit_title' => 'Nota di Credito #:number',

        'number' => 'Numero',
        'date' => 'Data',
        'customer' => 'Cliente',
        'select_customer' => 'Seleziona un cliente',
        'notes' => 'Note / Descrizione',

        // Related invoice section (DatiFattureCollegate)
        'related_invoice_section' => 'Fattura Originale di Riferimento',
        'related_invoice_hint' => 'Collegamento facoltativo alla fattura originale. Se compilato, viene incluso nel file XML (DatiFattureCollegate) secondo lo standard SDI.',
        'related_invoice_number' => 'N° Fattura Originale',
        'related_invoice_number_placeholder' => 'Es. FT-2026-001',
        'related_invoice_date' => 'Data Fattura Originale',

        'lines_section' => 'Righe',
        'line_description' => 'Descrizione',
        'line_quantity' => 'Q.tà',
        'line_unit_of_measure' => 'UM',
        'line_price' => 'Prezzo',
        'line_vat' => 'IVA',
        'add_line' => 'Aggiungi Riga',
        'no_lines' => 'Nessuna riga. Aggiungi una riga per iniziare.',

        'totals_section' => 'Totali',
        'net_total' => 'Imponibile',
        'vat_total' => 'IVA',
        'grand_total' => 'Totale',

        'download_xml' => 'Scarica XML',
        'send_sdi' => 'Invia a SDI',

        // Table column headers
        'col_number' => 'Numero',
        'col_date' => 'Data',
        'col_customer' => 'Cliente',
        'col_total' => 'Totale',
        'col_status' => 'Stato',

        // Summary stats
        'stat_total_notes' => 'Note di Credito totali',
        'stat_total_amount' => 'Totale importo',

        // Filter labels
        'filter_status' => 'Stato',

        // Toast messages
        'created' => 'Nota di Credito creata.',
        'updated' => 'Nota di Credito aggiornata.',
        'deleted' => 'Nota di Credito #:number eliminata',
        'filters_cleared' => 'Filtri azzerati.',
        'readonly_error' => 'Questo anno fiscale è concluso. Non è possibile modificare le note di credito.',
        'cannot_delete_sdi' => 'Non è possibile eliminare una nota di credito già processata dallo SDI.',
        'generation_error' => 'Errore durante la generazione/invio: :error',
    ],

    'contacts' => [
        'title' => 'Clienti & Fornitori',
        'create_title' => 'Crea Cliente',

        'main_data' => 'Dati Principali',
        'full_name' => 'Denominazione / Nome Cognome',
        'fiscal_data' => 'Dati Fiscali',

        'vat_number' => 'Partita IVA',
        'vat_number_hint' => 'Per contatto UE inserire con prefisso paese (es: DE123456789)',
        'tax_code' => 'Codice Fiscale',
        'tax_code_hint' => 'Solo per soggetti italiani',
        'sdi_code' => 'Codice Destinatario (SDI)',
        'sdi_code_hint' => '7 caratteri o 0000000 se si usa PEC',
        'pec' => 'PEC',
        'pec_hint' => 'Posta Elettronica Certificata (solo Italia)',
        'email' => 'Email',

        'address_section' => 'Indirizzo',
        'country' => 'Paese',
        'address' => 'Indirizzo',
        'postal_code' => 'CAP',
        'city' => 'Città',
        'province' => 'Provincia',

        // Table column headers
        'col_name' => 'Nome',
        'col_vat_number' => 'Partita IVA',
        'col_email' => 'E-mail',
        'col_city' => 'Città',

        // Toast messages
        'created' => 'Cliente creato.',
        'updated' => 'Cliente aggiornato.',
        'deleted' => ':name eliminato',
        'has_invoices' => 'Impossibile eliminare: il cliente ha fatture collegate.',
        'filters_cleared' => 'Filtri azzerati.',
    ],

    'sequences' => [
        'title' => 'Sezionali',
        'create_modal' => 'Crea Sezionale',
        'edit_modal' => 'Modifica Sezionale',

        // Table column headers
        'col_name' => 'Nome',
        'col_pattern' => 'Formato',
        'col_type' => 'Tipo',

        // Form labels
        'name' => 'Nome',
        'type' => 'Tipo',
        'pattern' => 'Formato',
        'pattern_hint' => 'Usa {SEQ} per il numero progressivo, {ANNO} per l\'anno corrente (es. FE-{SEQ}-{ANNO})',

        // Type labels
        'type_electronic_invoice' => 'Fatture Elettroniche',
        'type_purchase' => 'Acquisti',
        'type_self_invoice' => 'Autofatture',
        'type_proforma' => 'ProForma',
        'type_credit_note' => 'Note di Credito',
        'type_quote' => 'Preventivi',

        // Toast messages
        'created' => 'Sezionale creato.',
        'updated' => 'Sezionale aggiornato.',
        'deleted' => 'Sezionale :name eliminato.',
    ],

    'vat_rates' => [
        'title' => 'Aliquote IVA',
        'create_modal' => 'Crea Aliquota IVA',
        'edit_modal' => 'Modifica Aliquota IVA',

        // Table column headers
        'col_name' => 'Nome',
        'col_percent' => '%',
        'col_description' => 'Descrizione',

        // Form labels
        'name' => 'Nome',
        'percent' => 'Aliquota (%)',
        'description' => 'Descrizione',

        // Toast messages
        'created' => 'Aliquota IVA creata.',
        'updated' => 'Aliquota IVA aggiornata.',
        'deleted' => 'Aliquota IVA :name eliminata.',
    ],

    'settings' => [
        'company' => [
            'title' => 'Impostazioni Azienda',
            'readonly_title' => 'Sola lettura',
            'readonly_description' => 'Le impostazioni aziendali non sono modificabili in questo ambiente.',

            'general_info' => 'Informazioni Generali',
            'company_name' => 'Ragione Sociale',
            'vat_number' => 'Partita IVA',
            'tax_code' => 'Codice Fiscale',

            'address_section' => 'Indirizzo',
            'address' => 'Indirizzo',
            'postal_code' => 'CAP',
            'city' => 'Città',
            'province' => 'Provincia',
            'country' => 'Paese',

            'ateco_section' => 'Codici ATECO',
            'ateco_search_placeholder' => 'Cerca per codice o descrizione (es. 62, software...)',
            'ateco_no_results' => 'Nessun codice trovato.',

            'electronic_invoicing' => 'Fatturazione Elettronica',
            'fiscal_regime' => 'Regime Fiscale',
            'email' => 'Email Aziendale',
            'pec' => 'PEC',
            'sdi_code' => 'Codice SDI',

            'logo_section' => 'Logo Aziendale',
            'logo_upload' => 'Carica Logo',
            'logo_hint' => 'PNG o JPG, max 512 KB. Raccomandato: 400x200 px.',
            'logo_preview_alt' => 'Logo azienda',
            'remove_logo' => 'Rimuovi',
            'logo_removed' => 'Logo rimosso.',

            'fund_section' => 'Cassa Previdenziale',
            'fund_type' => 'Tipo Cassa',
            'fund_none' => 'Nessuna cassa',
            'fund_percent' => 'Aliquota Rivalsa',

            // Toast messages
            'saved' => 'Impostazioni azienda salvate.',
            'readonly_error' => 'Le impostazioni aziendali non sono modificabili in questo ambiente.',
        ],

        'invoice' => [
            'title' => 'Impostazioni Fattura',

            'defaults_section' => 'Predefiniti',
            'default_sequence' => 'Sezionale Predefinito (Vendite)',
            'default_vat_rate' => 'Aliquota IVA Predefinita',

            'withholding_section' => 'Ritenuta d\'Acconto',
            'withholding_tax_enabled' => "Abilita automaticamente Ritenuta d\'Acconto",
            'withholding_tax_percent' => 'Percentuale di Default (%)',

            'fund_section' => 'Cassa Previdenziale',
            'fund_enabled' => 'Abilita Cassa per Default nelle Nuove Fatture',
            'fund_vat_rate' => 'Aliquota IVA Rivalsa',
            'fund_has_deduction' => 'Rivalsa Soggetta a Ritenuta d\'Acconto',

            'stamp_duty_section' => 'Bollo Virtuale',
            'auto_stamp_duty' => 'Applica Bollo Automaticamente',
            'stamp_duty_threshold' => 'Soglia (€)',

            'payments_section' => 'Pagamenti',
            'default_payment_method' => 'Metodo di Pagamento Predefinito',
            'default_payment_terms' => 'Termini di Pagamento Predefiniti',
            'default_bank_name' => 'Banca Predefinita',
            'default_iban' => 'IBAN Predefinito',

            'vat_section' => 'IVA',
            'default_vat_payability' => 'Esigibilita IVA Predefinita',
            'default_split_payment' => 'Split Payment per Default',

            'other_section' => 'Altro',
            'default_notes' => 'Note / Causale Predefinite',

            // Toast messages
            'saved' => 'Impostazioni fattura salvate.',
        ],

        'email' => [
            'title' => 'Impostazioni Email',
            'readonly_title' => 'Sola lettura',
            'readonly_description' => 'Le impostazioni email non sono modificabili in questo ambiente.',
            'readonly_error' => 'Le impostazioni email non sono modificabili in questo ambiente.',

            'smtp_section' => 'Configurazione SMTP',
            'smtp_host' => 'Host SMTP',
            'smtp_port' => 'Porta',
            'smtp_username' => 'Username',
            'smtp_password' => 'Password',
            'smtp_encryption' => 'Crittografia',
            'encryption_none' => 'Nessuna',

            'sender_section' => 'Mittente',
            'from_address' => 'Indirizzo mittente',
            'from_name' => 'Nome mittente',

            'template_sales' => 'Template Fatture',
            'template_proforma' => 'Template Proforma',
            'template_subject' => 'Oggetto',
            'template_body' => 'Corpo del messaggio',
            'auto_send' => 'Invio automatico',

            'smtp_managed_by_env_title' => 'SMTP gestito dalla piattaforma',
            'smtp_managed_by_env_description' => 'In questo ambiente la configurazione SMTP viene gestita automaticamente dall\'infrastruttura di hosting.',

            // Toast messages
            'saved' => 'Impostazioni email salvate.',
        ],

        'services' => [
            'title' => 'Servizi',
            'readonly_title' => 'Sola lettura',
            'readonly_description' => 'Le impostazioni dei servizi non sono modificabili in questo ambiente.',
            'readonly_error' => 'Le impostazioni dei servizi non sono modificabili in questo ambiente.',

            'backup' => [
                'title' => 'Backup',
                'subtitle' => 'Backup automatico del database e dei documenti su storage S3.',
                'managed_by_env_title' => 'Backup gestito dalla piattaforma',
                'managed_by_env_description' => 'In questo ambiente il backup viene gestito automaticamente dall\'infrastruttura di hosting.',
                'enabled' => 'Abilita backup automatico',

                'schedule_section' => 'Pianificazione',
                'frequency' => 'Frequenza',
                'frequency_daily' => 'Giornaliero',
                'frequency_weekly' => 'Settimanale',
                'frequency_monthly' => 'Mensile',
                'time' => 'Orario',
                'day_of_week' => 'Giorno della settimana',
                'day_of_month' => 'Giorno del mese',

                's3_section' => 'Destinazione S3',
                'aws_access_key_id' => 'Access Key ID',
                'aws_secret_access_key' => 'Secret Access Key',
                'aws_default_region' => 'Regione',
                'aws_bucket' => 'Bucket',
                'aws_endpoint' => 'Endpoint personalizzato',
                'aws_endpoint_hint' => 'Lascia vuoto per AWS S3. Compila per provider compatibili (MinIO, Backblaze, DigitalOcean Spaces).',
                'aws_use_path_style_endpoint' => 'Usa URL in stile percorso',
                'aws_use_path_style_endpoint_hint' => 'Necessario per alcuni provider S3 compatibili come MinIO.',

                'test_connection' => 'Verifica connessione',
                'test_connection_hint' => 'Salva prima le credenziali, poi verifica la connessione.',
                'connection_success' => 'Connessione S3 riuscita.',
                'connection_error' => 'Impossibile connettersi al bucket S3. Verifica le credenziali.',

                'saved' => 'Impostazioni backup salvate.',
            ],

            'monitoring' => [
                'title' => 'Monitoraggio errori',
                'subtitle' => 'Tracciamento errori e performance via Sentry o servizi compatibili (GlitchTip, ecc.).',
                'managed_by_env_title' => 'Monitoraggio gestito dalla piattaforma',
                'managed_by_env_description' => 'In questo ambiente il monitoraggio viene gestito automaticamente dall\'infrastruttura di hosting.',
                'enabled' => 'Abilita monitoraggio errori',
                'dsn' => 'DSN',
                'dsn_hint' => 'Il DSN ti viene fornito dalla dashboard Sentry o GlitchTip dopo aver creato un progetto.',
                'environment' => 'Ambiente',
                'traces_sample_rate' => 'Campionamento performance',
                'traces_sample_rate_hint' => '0 = disabilitato, 1 = 100% delle richieste. Usare valori bassi in produzione (es. 0.1).',
                'saved' => 'Impostazioni monitoraggio salvate.',
            ],
        ],

        'sdi' => [
            'no_provider_title' => 'Nessun provider SDI installato',
            'no_provider_description' => 'Installa un plugin provider (es. OpenAPI) per abilitare la fatturazione elettronica.',
        ],

        'plugins' => [
            'title' => 'Plugin',
            'subtitle' => 'Plugin installati e attivi',
            'empty' => 'Nessun plugin installato.',
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            'author' => 'Autore',
            'activate' => 'Attiva',
            'deactivate' => 'Disattiva',
            'locked' => 'Bloccato',
            'activated' => 'Plugin attivato. Ricarica la pagina per applicare le modifiche.',
            'deactivated' => 'Plugin disattivato. Ricarica la pagina per applicare le modifiche.',
            'deactivate_confirm' => 'Disattivare :name? Le sue funzionalita non saranno disponibili fino alla riattivazione.',
            'restart_hint' => 'Le modifiche di attivazione hanno effetto al prossimo caricamento della pagina.',
        ],
    ],

    'setup' => [
        'subtitle' => 'Configurazione iniziale',
        'step_account' => 'Account',
        'step_company' => 'Azienda',
        'step_address' => 'Indirizzo',
        'account_name' => 'Nome',
        'account_email' => 'Email',
        'account_password' => 'Password',
        'account_password_confirm' => 'Conferma Password',
        'fiscal_regime' => 'Regime Fiscale',
        'invoice_defaults' => 'Impostazioni Fattura',
        'auto_stamp_duty' => 'Bollo Automatico',
        'auto_stamp_duty_hint' => 'Applica automaticamente il bollo di €2 sulle fatture esenti IVA sopra €77,47',
        'withholding_tax_enabled' => "Ritenuta d'Acconto",
        'withholding_tax_hint' => "Abilita la ritenuta d'acconto sulle nuove fatture",
        'next' => 'Avanti',
        'back' => 'Indietro',
        'complete' => 'Completa Setup',
        'step_account_desc' => 'Crea il tuo account amministratore',
        'step_company_desc' => 'Inserisci i dati della tua azienda',
        'step_address_desc' => 'Ultimo passo: indirizzo e fatturazione elettronica',
    ],

    'landing' => [
        'tagline' => 'Fatturazione elettronica italiana, semplice e veloce',
        'feature_xml_title' => 'XML conforme',
        'feature_xml_desc' => 'Genera fatture elettroniche nel formato standard Fattura PA/B2B',
        'feature_sdi_title' => 'Invio diretto SDI',
        'feature_sdi_desc' => 'Trasmetti le fatture al Sistema di Interscambio con un click',
        'feature_dashboard_title' => 'Dashboard e report',
        'feature_dashboard_desc' => 'Monitora fatturato, IVA e stato delle fatture in tempo reale',
        'trust_line' => 'Usato da professionisti e piccole imprese in tutta Italia',
    ],

    'auth' => [
        'email' => 'Email',
        'password' => 'Password',
        'remember_me' => 'Ricordami',
        'login' => 'Accedi',
        'welcome_back' => 'Bentornato',
        'login_subtitle' => 'Accedi al tuo account per continuare',
        'no_account' => 'Non hai un account?',
        'setup_link' => 'Configura Fatturino',
    ],

    'imports' => [
        'title' => 'Importazioni',

        'xml_section' => 'Fattura Elettronica XML',
        'xml_section_desc' => 'Importa fatture dal formato XML standard italiano',

        'platforms_section' => 'Piattaforme',
        'platforms_section_desc' => 'Importa dati da software di fatturazione di terze parti',

        'xml_sales_title' => 'XML Fatture Vendite',
        'xml_sales_desc' => 'Importa fatture attive da file XML Fattura Elettronica (.xml, .p7m)',

        'xml_purchase_title' => 'XML Fatture Acquisti',
        'xml_purchase_desc' => 'Importa fatture passive da file XML Fattura Elettronica (.xml, .p7m)',

        'xml_self_invoice_title' => 'XML Autofatture',
        'xml_self_invoice_desc' => 'Importa autofatture (TD17, TD18, TD19, TD28) da file XML Fattura Elettronica (.xml, .p7m)',

        'fattura24_contacts_title' => 'Fattura24: Rubrica',
        'fattura24_contacts_desc' => 'Importa clienti e fornitori dall\'esportazione CSV della rubrica di Fattura24',
        'aruba_contacts_title' => 'Aruba: Rubrica',
        'aruba_contacts_desc' => 'Importa clienti e fornitori dalla rubrica di Aruba Fatturazione',
        'fic_contacts_title' => 'Fatture in Cloud: Rubrica',
        'fic_contacts_desc' => 'Importa clienti e fornitori dalla rubrica di Fatture in Cloud',

        'start_import' => 'Importa',
        'run_import' => 'Avvia Importazione',
        'import_another' => 'Importa un altro',

        'xml_file_label' => 'File Fattura Elettronica',
        'xml_file_hint' => 'Formati supportati: .xml, .p7m (con firma digitale), .zip (archivio con più file XML)',
        'csv_file_label' => 'File CSV Fattura24',
        'csv_file_hint' => 'Esporta la rubrica da Fattura24 in formato CSV',
        'select_sequence' => 'Sezionale',
        'select_sequence_placeholder' => 'Seleziona un sezionale...',
        'update_existing' => 'Aggiorna contatti esistenti',
        'update_existing_hint' => 'Se abilitato, aggiorna i contatti già presenti con la stessa Partita IVA',

        'completed_no_errors' => 'Importazione completata senza errori.',
        'completed_with_errors' => 'Importazione completata con alcuni errori.',

        'stat_invoices_imported' => 'Fatture importate',
        'stat_contacts_created' => 'Contatti creati',
        'stat_total' => 'Totale righe',
        'stat_imported' => 'Importati',
        'stat_updated' => 'Aggiornati',
        'stat_skipped' => 'Saltati',
        'stat_errors' => 'Errori',

        'error_details' => 'Dettaglio errori',
        'no_sequence_available' => 'Nessun sezionale disponibile per questa categoria. Configurane uno in Impostazioni > Sezionali.',
        'zip_open_error' => 'Impossibile aprire il file ZIP.',
        'zip_no_xml' => 'Il file ZIP non contiene file XML o P7M validi.',
    ],

    'wip' => [
        'title' => 'Lavori in Corso',
        'message' => 'Questa pagina non è ancora pronta',
        'back_to_dashboard' => 'Torna alla Dashboard',
    ],

    'email' => [
        // Modal and actions
        'send_email' => 'Invia Email',
        'send' => 'Invia',
        'recipient' => 'Destinatario',
        'cc' => 'Copia (CC)',
        'subject' => 'Oggetto',
        'body' => 'Messaggio',
        'confirm_send' => 'Inviare l\'email al cliente?',
        'log_title' => 'Invii Email',

        // Status enum labels
        'status_queued' => 'In coda',
        'status_sent' => 'Inviata',
        'status_failed' => 'Errore',

        // Toast messages
        'sent_success' => 'Email inviata con successo.',
        'send_error' => 'Errore invio email: :error',
        'send_not_allowed' => 'Invio email non disponibile in modalita demo.',
        'no_recipient' => 'Nessun indirizzo email disponibile per questo contatto.',
        'test_connection' => 'Testa Connessione',
        'test_success' => 'Email di test inviata con successo.',
        'test_error' => 'Errore connessione SMTP:',
        'test_error_no_recipient' => 'Nessun indirizzo mittente configurato. Imposta il campo "Indirizzo mittente" prima di testare.',

        // Test email content
        'test_subject' => 'Test connessione SMTP - Fatturino',
        'test_body' => 'Questo è un messaggio di test inviato da Fatturino per verificare la configurazione SMTP.',

        // Helper text
        'test_connection_hint' => 'Invia un\'email di prova all\'indirizzo mittente per verificare la connessione.',
        'placeholders_hint' => 'Variabili disponibili: {CLIENTE}, {NUMERO_DOCUMENTO}, {DATA_DOCUMENTO}, {IMPORTO_NETTO}, {IMPORTO_IVA}, {IMPORTO_TOTALE}, {AZIENDA}, {PARTITA_IVA_AZIENDA}, {EMAIL_CLIENTE}',
        'attach_pdf' => 'Allega PDF cortesia',
    ],

    'pdf' => [
        'courtesy_title' => 'FATTURA DI CORTESIA',
        'proforma_title' => 'FATTURA PROFORMA',
        'credit_note_title' => 'NOTA DI CREDITO',

        'supplier_section' => 'Fornitore',
        'customer_section' => 'Destinatario',

        'invoice_number' => 'Numero',
        'invoice_date' => 'Data',
        'due_date' => 'Scadenza',
        'document_type' => 'Tipo Documento',

        'line_description' => 'Descrizione',
        'line_quantity' => 'Q.ta',
        'line_unit' => 'UM',
        'line_unit_price' => 'Prezzo Unit.',
        'line_discount' => 'Sconto',
        'line_vat' => 'IVA',
        'line_amount' => 'Importo',

        'vat_summary' => 'Riepilogo IVA',
        'vat_rate' => 'Aliquota',
        'taxable' => 'Imponibile',
        'vat_amount' => 'Imposta',

        'payment_info' => 'Pagamento',
        'payment_method' => 'Metodo',
        'bank' => 'Banca',
        'iban' => 'IBAN',

        'net_total' => 'Imponibile',
        'fund_contribution' => 'Rivalsa Previdenziale (:percent%)',
        'vat_total' => 'IVA',
        'gross_total' => 'Totale',
        'stamp_duty' => 'Marca da Bollo',
        'withholding_tax' => "Ritenuta d'Acconto (:percent%)",
        'split_payment_deduction' => 'Scissione Pagamenti (IVA)',
        'net_due' => 'NETTO A PAGARE',

        'notes' => 'Note',

        'sdi_disclaimer' => 'Documento privo di rilevanza fiscale ai sensi dell\'art. 21 del DPR 633/72. La fattura originale e\' stata inviata in formato elettronico tramite il Sistema di Interscambio (SDI).',
    ],

    'errors' => [
        '404_title' => 'Pagina non trovata',
        '404_desc' => 'La pagina che stai cercando non esiste o è stata spostata.',
        '500_title' => 'Errore del server',
        '500_desc' => 'Qualcosa è andato storto. Riprova tra qualche istante.',
        'go_home' => 'Torna alla Home',
        'go_back' => 'Torna Indietro',
        'retry' => 'Riprova',
    ],

    'payments' => [
        'title' => 'Pagamenti',
        'registered_payments' => 'Pagamenti registrati',
        'no_payments' => 'Nessun pagamento registrato.',
        'remaining_balance' => 'Saldo residuo',
        'add_payment' => 'Registra pagamento',
        'record_payment' => 'Registra',
        'mark_as_paid' => 'Segna come saldato',
        'amount' => 'Importo',
        'date' => 'Data',
        'method' => 'Metodo',
        'method_optional' => 'Metodo (opzionale)',
        'reference' => 'Riferimento',
        'reference_placeholder' => 'CRO, TRN, n. assegno...',
        'notes' => 'Note',
        'delete_confirm' => 'Eliminare questo pagamento?',
        'delete_yes' => 'Elimina',
        'payment_added' => 'Pagamento registrato.',
        'payment_deleted' => 'Pagamento eliminato.',
    ],

    'days' => [
        'sunday' => 'Domenica',
        'monday' => 'Lunedi',
        'tuesday' => 'Martedi',
        'wednesday' => 'Mercoledi',
        'thursday' => 'Giovedi',
        'friday' => 'Venerdi',
        'saturday' => 'Sabato',
    ],

    'conservation' => [
        // Setup wizard section
        'section_title' => 'Conservazione a norma',
        'setup_description' => 'Fatturino non gestisce la conservazione a norma di legge delle fatture elettroniche. Devi aderire gratuitamente al servizio di conservazione dell\'Agenzia delle Entrate dalla tua area Fatture e Corrispettivi.',
        'setup_acknowledge_label' => 'Confermo di aver attivato (o di attivare a breve) il servizio di conservazione gratuito dell\'Agenzia delle Entrate',
        'setup_acknowledge_hint' => 'Senza adesione le fatture trasmesse non vengono conservate a norma.',

        // Banner on Fatturazione Elettronica page
        'banner_title' => 'Conservazione a norma non confermata',
        'banner_description' => 'Fatturino non conserva le fatture a norma di legge. Aderisci al servizio gratuito dell\'Agenzia delle Entrate da Fatture e Corrispettivi e poi conferma qui sotto.',
        'link_label' => 'Vai al servizio AdE',
        'acknowledge_button' => 'Conferma adesione',

        // Confirmation state
        'acknowledged_title' => 'Conservazione a norma: adesione confermata',
        'acknowledged_description' => 'Hai dichiarato di aver aderito al servizio di conservazione dell\'Agenzia delle Entrate. La conservazione delle fatture e\' gestita interamente da AdE.',
        'acknowledged_toast' => 'Adesione alla conservazione AdE registrata.',
    ],
];
