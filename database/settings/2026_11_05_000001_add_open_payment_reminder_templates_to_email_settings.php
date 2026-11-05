<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('email.template_payment_reminder_open_subject', 'Sollecito di pagamento: fattura n. {NUMERO_DOCUMENTO}');
        $this->migrator->add('email.template_payment_reminder_open_body', "Gentile {CLIENTE},\n\nrisulta ancora aperta la fattura n. {NUMERO_DOCUMENTO} per un importo residuo di {IMPORTO_RESIDUO}.\n\nLa invitiamo a verificare il pagamento. Se ha già effettuato il pagamento, la preghiamo di ignorare questo sollecito.\n\nCordiali saluti,\n{AZIENDA}");
    }
};
