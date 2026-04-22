<?php

namespace App\Livewire\Settings;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\Capability;
use App\Services\DocumentMailer;
use App\Settings\EmailSettings;
use Livewire\Component;
use Mary\Traits\Toast;

class Email extends Component
{
    use Toast;

    // SMTP settings
    public ?string $smtp_host = null;
    public ?int $smtp_port = null;
    public ?string $smtp_username = null;
    public ?string $smtp_password = null;
    public ?string $smtp_encryption = null;
    public ?string $from_address = null;
    public ?string $from_name = null;

    // Sales invoice template
    public string $template_sales_subject = '';
    public string $template_sales_body = '';
    public bool $auto_send_sales = false;

    // Proforma invoice template
    public string $template_proforma_subject = '';
    public string $template_proforma_body = '';
    public bool $auto_send_proforma = false;

    // True when the current environment blocks editing
    public bool $readonly = false;

    // True when SMTP config is managed exclusively via environment variables
    public bool $smtpManagedByEnv = false;

    public function mount(EmailSettings $settings, EnvironmentCapabilities $capabilities): void
    {
        $this->readonly = $capabilities->cannot(Capability::EditEmailSettings);
        $this->smtpManagedByEnv = (bool) config('email.managed_by_env');

        $this->smtp_host       = $settings->smtp_host;
        $this->smtp_port       = $settings->smtp_port;
        $this->smtp_username   = $settings->smtp_username;
        $this->smtp_password   = $settings->smtp_password;
        $this->smtp_encryption = $settings->smtp_encryption;
        $this->from_address    = $settings->from_address;
        $this->from_name       = $settings->from_name;

        $this->template_sales_subject    = $settings->template_sales_subject;
        $this->template_sales_body       = $settings->template_sales_body;
        $this->auto_send_sales           = $settings->auto_send_sales;

        $this->template_proforma_subject = $settings->template_proforma_subject;
        $this->template_proforma_body    = $settings->template_proforma_body;
        $this->auto_send_proforma        = $settings->auto_send_proforma;
    }

    public function save(EmailSettings $settings, EnvironmentCapabilities $capabilities): void
    {
        if ($capabilities->cannot(Capability::EditEmailSettings)) {
            $this->error(__('app.settings.email.readonly_error'));
            return;
        }

        $this->validate([
            'smtp_port'       => 'nullable|integer|min:1|max:65535',
            'from_address'    => 'nullable|email',
            'smtp_encryption' => 'nullable|in:tls,ssl',
            'template_sales_subject'    => 'required|string',
            'template_sales_body'       => 'required|string',
            'template_proforma_subject' => 'required|string',
            'template_proforma_body'    => 'required|string',
        ]);

        // Skip SMTP fields when the environment manages them exclusively
        if (! config('email.managed_by_env')) {
            $settings->smtp_host       = $this->smtp_host ?: null;
            $settings->smtp_port       = $this->smtp_port ?: null;
            $settings->smtp_username   = $this->smtp_username ?: null;
            $settings->smtp_password   = $this->smtp_password ?: null;
            $settings->smtp_encryption = $this->smtp_encryption ?: null;
            $settings->from_address    = $this->from_address ?: null;
            $settings->from_name       = $this->from_name ?: null;
        }

        $settings->template_sales_subject    = $this->template_sales_subject;
        $settings->template_sales_body       = $this->template_sales_body;
        $settings->auto_send_sales           = $this->auto_send_sales;

        $settings->template_proforma_subject = $this->template_proforma_subject;
        $settings->template_proforma_body    = $this->template_proforma_body;
        $settings->auto_send_proforma        = $this->auto_send_proforma;

        $settings->save();

        $this->success(__('app.settings.email.saved'));
    }

    public function testConnection(DocumentMailer $mailer): void
    {
        // Persist current form values temporarily so the mailer sees them
        $settings = app(EmailSettings::class);
        $settings->smtp_host       = $this->smtp_host ?: null;
        $settings->smtp_port       = $this->smtp_port ?: null;
        $settings->smtp_username   = $this->smtp_username ?: null;
        $settings->smtp_password   = $this->smtp_password ?: null;
        $settings->smtp_encryption = $this->smtp_encryption ?: null;
        $settings->from_address    = $this->from_address ?: null;
        $settings->from_name       = $this->from_name ?: null;

        $error = $mailer->testConnection();

        if ($error === null) {
            $this->success(__('app.email.test_success'));
        } else {
            $this->error(__('app.email.test_error') . ' ' . $error);
        }
    }

    public function render()
    {
        return view('livewire.settings.email');
    }
}
