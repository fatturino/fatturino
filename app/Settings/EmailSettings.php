<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EmailSettings extends Settings
{
    public string $mail_provider;

    // SMTP configuration (overrides .env when non-null)
    public ?string $smtp_host;

    public ?int $smtp_port;

    public ?string $smtp_username;

    public ?string $smtp_password;

    public ?string $smtp_encryption;

    public ?string $from_address;

    public ?string $from_name;

    public ?string $scaleway_tem_region;

    public ?string $scaleway_tem_project_id;

    public ?string $scaleway_tem_secret_key;

    // Email templates for sales invoices
    public string $template_sales_subject;

    public string $template_sales_body;

    // Manual payment reminder templates for sales invoices.
    public string $template_payment_reminder_upcoming_subject;

    public string $template_payment_reminder_upcoming_body;

    public string $template_payment_reminder_overdue_subject;

    public string $template_payment_reminder_overdue_body;

    // Email templates for proforma invoices
    public string $template_proforma_subject;

    public string $template_proforma_body;

    // Auto-send after key actions
    public bool $auto_send_sales;

    public bool $auto_send_proforma;

    /**
     * Define the settings group name
     */
    public static function group(): string
    {
        return 'email';
    }
}
