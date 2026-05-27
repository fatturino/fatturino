<?php

return [
    'title' => 'OpenAPI SDI Configuration',
    'service_active' => 'Service Active',
    'service_inactive' => 'Service Inactive',
    'readonly_title' => 'Read-Only',
    'readonly_description' => 'OpenAPI settings cannot be modified in this environment.',

    'api_token' => 'API Token',
    'api_token_hint' => 'The token provided by OpenAPI',
    'sandbox_mode' => 'Sandbox Mode',
    'sandbox_hint' => 'Enable to send test invoices',
    'sdi_code' => 'Company SDI Code',
    'sdi_code_hint' => 'Your recipient code (optional)',

    'check_connection' => 'Check Connection',
    'deactivate' => 'Deactivate Service',
    'deactivate_confirm' => 'Are you sure you want to deactivate the service? You will be able to modify the parameters.',
    'activate' => 'Activate Service',

    'instructions_title' => 'Instructions',
    'instructions_intro' => 'To obtain your API Token:',
    'instructions_step_2' => 'Access the Developer Console',
    'instructions_step_3' => 'Generate a new API token with Electronic Invoicing permissions',

    // Toast messages
    'saved' => 'OpenAPI settings saved.',
    'activated' => 'Electronic invoicing service activated.',
    'deactivated' => 'Service deactivated. You can now modify the parameters.',
    'connection_ok' => 'Connection successful! The service is active.',
    'connection_ok_inactive' => 'Connection successful, but the service is not active for this VAT number.',
    'connection_failed' => 'Connection failed: :error',
    'readonly_error' => 'OpenAPI settings cannot be modified in this environment.',
    'deactivate_first' => 'Deactivate the service before modifying the parameters.',
    'vat_missing' => 'VAT number not set. Please configure Company Settings first.',
    'email_missing' => 'Company email not set. Please configure Company Settings first.',
    'registration_sent' => 'Registration submitted. Check the company email to complete activation, then click "Activate Service" again.',
    'registration_failed' => 'Registration failed: :error',
    'status_check_failed' => 'Unable to verify service status: :error',

    // Webhook / callback configuration
    'activate_first' => 'Activate the service before configuring callbacks.',
    'callbacks_configured' => 'Webhooks configured. You will receive supplier invoices and SDI notifications.',
    'callbacks_failed' => 'Webhook configuration failed: :error. You can retry from the Webhook section.',
    'tab_service' => 'Service',
    'tab_webhook' => 'Webhooks',
    'webhook_title' => 'SDI Webhooks',
    'webhook_active' => 'Webhooks Active',
    'webhook_events' => 'Configured events',
    'webhook_not_configured' => 'Webhooks Not Configured',
    'webhook_reconfigure' => 'Reconfigure Webhooks',
    'webhook_url' => 'Webhook URL (optional)',
    'webhook_url_hint' => 'Public URL for receiving webhooks (e.g. Cloudflare tunnel). If empty, APP_URL is used.',

    'simulate_title' => 'Webhook Simulation',
    'simulate_description' => 'Send a simulated event via the OpenAPI sandbox to test webhook reception.',
    'simulate_type' => 'Event type',
    'simulate_send' => 'Simulate',
    'simulate_success' => 'Simulation ":type" sent. Check the logs to verify reception.',
    'simulate_failed' => 'Simulation failed: :error',
    'simulate_sandbox_only' => 'Simulation is only available in sandbox mode.',
];
