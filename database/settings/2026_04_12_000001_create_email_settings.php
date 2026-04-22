<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // SMTP configuration (null = use .env defaults)
        $this->migrator->add('email.smtp_host', null);
        $this->migrator->add('email.smtp_port', null);
        $this->migrator->add('email.smtp_username', null);
        $this->migrator->add('email.smtp_password', null);
        $this->migrator->add('email.smtp_encryption', null);
        $this->migrator->add('email.from_address', null);
        $this->migrator->add('email.from_name', null);

        // Default template for sales invoices
        $this->migrator->add('email.template_sales_subject', 'Fattura n. {NUMERO_DOCUMENTO} del {DATA_DOCUMENTO}');
        $this->migrator->add('email.template_sales_body', "Gentile {CLIENTE},\n\nLe inviamo in allegato la fattura n. {NUMERO_DOCUMENTO} del {DATA_DOCUMENTO} per un importo totale di {IMPORTO_TOTALE}.\n\nCordiali saluti,\n{AZIENDA}");

        // Default template for proforma invoices
        $this->migrator->add('email.template_proforma_subject', 'Fattura Proforma n. {NUMERO_DOCUMENTO} del {DATA_DOCUMENTO}');
        $this->migrator->add('email.template_proforma_body', "Gentile {CLIENTE},\n\nLe inviamo la fattura proforma n. {NUMERO_DOCUMENTO} del {DATA_DOCUMENTO} per un importo totale di {IMPORTO_TOTALE}.\n\nCordiali saluti,\n{AZIENDA}");

        // Auto-send toggles
        $this->migrator->add('email.auto_send_sales', false);
        $this->migrator->add('email.auto_send_proforma', false);
    }
};
