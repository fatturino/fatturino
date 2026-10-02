<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('email.template_payment_reminder_upcoming_subject', 'Promemoria di pagamento: fattura n. {NUMERO_DOCUMENTO} in scadenza il {DATA_SCADENZA}');
        $this->migrator->add('email.template_payment_reminder_upcoming_body', "Gentile {CLIENTE},\n\nle ricordiamo che la fattura n. {NUMERO_DOCUMENTO}, per un importo residuo di {IMPORTO_RESIDUO}, scade il {DATA_SCADENZA}.\n\nSe ha già effettuato il pagamento, la preghiamo di ignorare questo promemoria.\n\nCordiali saluti,\n{AZIENDA}");
        $this->migrator->add('email.template_payment_reminder_overdue_subject', 'Sollecito di pagamento: fattura n. {NUMERO_DOCUMENTO} scaduta il {DATA_SCADENZA}');
        $this->migrator->add('email.template_payment_reminder_overdue_body', "Gentile {CLIENTE},\n\nrisulta ancora aperta la fattura n. {NUMERO_DOCUMENTO}, scaduta il {DATA_SCADENZA}, per un importo residuo di {IMPORTO_RESIDUO}.\n\nLa invitiamo a verificare il pagamento. Se ha già effettuato il pagamento, la preghiamo di ignorare questo sollecito.\n\nCordiali saluti,\n{AZIENDA}");
    }
};
