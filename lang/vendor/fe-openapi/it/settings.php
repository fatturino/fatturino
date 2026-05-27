<?php

return [
    'title' => 'Configurazione OpenAPI SDI',
    'service_active' => 'Servizio Attivo',
    'service_inactive' => 'Servizio Non Attivo',
    'readonly_title' => 'Sola lettura',
    'readonly_description' => 'Le impostazioni OpenAPI non sono modificabili in questo ambiente.',

    'api_token' => 'API Token',
    'api_token_hint' => 'Il token fornito da OpenAPI',
    'sandbox_mode' => 'Modalità Sandbox',
    'sandbox_hint' => 'Abilita per inviare fatture di test',
    'sdi_code' => 'Codice SDI Azienda',
    'sdi_code_hint' => 'Il tuo codice destinatario (opzionale)',

    'check_connection' => 'Verifica Connessione',
    'deactivate' => 'Disattiva Servizio',
    'deactivate_confirm' => 'Sei sicuro di voler disattivare il servizio? Potrai modificare i parametri.',
    'activate' => 'Attiva Servizio',

    'instructions_title' => 'Istruzioni',
    'instructions_intro' => 'Per ottenere il tuo API Token:',
    'instructions_step_2' => 'Accedi alla Console Sviluppatori',
    'instructions_step_3' => 'Genera un nuovo token API con permessi Fatturazione Elettronica',

    // Toast messages
    'saved' => 'Impostazioni OpenAPI salvate.',
    'activated' => 'Servizio di fatturazione elettronica attivato.',
    'deactivated' => 'Servizio disattivato. Ora puoi modificare i parametri.',
    'connection_ok' => 'Connessione riuscita! Il servizio è attivo.',
    'connection_ok_inactive' => 'Connessione riuscita, ma il servizio non è attivo per questa Partita IVA.',
    'connection_failed' => 'Connessione fallita: :error',
    'readonly_error' => 'Le impostazioni OpenAPI non sono modificabili in questo ambiente.',
    'deactivate_first' => 'Disattiva il servizio prima di modificare i parametri.',
    'vat_missing' => 'Partita IVA non impostata. Configura prima i Dati Aziendali.',
    'email_missing' => 'Email aziendale non impostata. Configura prima i Dati Aziendali.',
    'registration_sent' => 'Registrazione inviata. Controlla l\'email aziendale per completare l\'attivazione, poi clicca di nuovo "Attiva Servizio".',
    'registration_failed' => 'Registrazione fallita: :error',
    'status_check_failed' => 'Impossibile verificare lo stato del servizio: :error',

    // Webhook / callback configuration
    'activate_first' => 'Attiva il servizio prima di configurare i callback.',
    'callbacks_configured' => 'Webhook configurati. Riceverai fatture passive e notifiche SDI.',
    'callbacks_failed' => 'Configurazione webhook fallita: :error. Puoi riprovare dalla sezione Webhook.',
    'tab_service' => 'Servizio',
    'tab_webhook' => 'Webhook',
    'webhook_title' => 'Webhook SDI',
    'webhook_active' => 'Webhook Attivi',
    'webhook_events' => 'Eventi configurati',
    'webhook_not_configured' => 'Webhook Non Configurati',
    'webhook_reconfigure' => 'Riconfigura Webhook',
    'webhook_url' => 'URL Webhook (opzionale)',
    'webhook_url_hint' => 'URL pubblico per ricevere i webhook (es. tunnel Cloudflare). Se vuoto, viene usato APP_URL.',

    'simulate_title' => 'Simulazione Webhook',
    'simulate_description' => 'Invia un evento simulato tramite la sandbox di OpenAPI per testare la ricezione dei webhook.',
    'simulate_type' => 'Tipo evento',
    'simulate_send' => 'Simula',
    'simulate_success' => 'Simulazione ":type" inviata. Controlla i log per verificare la ricezione.',
    'simulate_failed' => 'Simulazione fallita: :error',
    'simulate_sandbox_only' => 'La simulazione è disponibile solo in modalità sandbox.',
];
