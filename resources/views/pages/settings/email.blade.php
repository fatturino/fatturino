<?php

use App\Contracts\EnvironmentCapabilities;
use App\Services\DocumentMailer;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public string $mail_provider = 'smtp';

    public string $smtp_host = '';

    public string $smtp_port = '';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_encryption = '';

    public string $scaleway_tem_region = 'fr-par';

    public string $scaleway_tem_project_id = '';

    public string $scaleway_tem_secret_key = '';

    public string $from_address = '';

    public string $from_name = '';

    public string $template_sales_subject = '';

    public string $template_sales_body = '';

    public bool $auto_send_sales = false;

    public string $template_proforma_subject = '';

    public string $template_proforma_body = '';

    public bool $auto_send_proforma = false;

    public function mount(EmailSettings $settings): void
    {
        foreach (array_diff(array_keys($this->rules()), $this->secretFields()) as $field) {
            $this->{$field} = $settings->{$field} ?? (is_bool($this->{$field}) ? false : '');
        }
    }

    public function save(EmailSettings $settings): void
    {
        $this->ensureAllowed();
        $validated = $this->validate();
        $secrets = collect($this->secretFields())->mapWithKeys(fn ($field) => [$field => $validated[$field] ?? null]);
        foreach ($this->secretFields() as $field) {
            unset($validated[$field]);
        }
        $settings->fill($validated);
        foreach ($secrets as $field => $value) {
            if (filled($value)) {
                $settings->{$field} = $value;
            }
        }
        $settings->save();
        session()->flash('success', 'Impostazioni email salvate.');
    }

    public function testConnection(EmailSettings $settings): void
    {
        try {
            $data = $this->smtpManagedByEnv() ? $this->validate(collect($this->rules())->only(['from_address', 'from_name'])->all()) : $this->validate();
            $temporary = clone $settings;
            $temporary->fill($data);
            foreach ($this->secretFields() as $field) {
                if (! filled($data[$field] ?? null)) {
                    $temporary->{$field} = $settings->{$field};
                }
            }
            $error = (new DocumentMailer($temporary, app(CompanySettings::class)))->testConnection();
            if ($error) {
                throw new RuntimeException($error);
            }
            session()->flash('success', 'Connessione email riuscita.');
        } catch (Throwable $exception) {
            Log::warning('Email connection test failed.', ['exception' => $exception]);
            $this->addError('smtp', 'Impossibile verificare la connessione email. Controlla i dati configurati.');
        }
    }

    protected function rules(): array
    {
        $rules = ['from_address' => 'nullable|email', 'from_name' => 'nullable|string', 'template_sales_subject' => 'nullable|string', 'template_sales_body' => 'nullable|string', 'auto_send_sales' => 'boolean', 'template_proforma_subject' => 'nullable|string', 'template_proforma_body' => 'nullable|string', 'auto_send_proforma' => 'boolean'];

        return $this->smtpManagedByEnv() ? $rules : array_merge($rules, ['mail_provider' => 'required|in:smtp,scaleway_tem', 'smtp_host' => 'nullable|string', 'smtp_port' => 'nullable|string', 'smtp_username' => 'nullable|string', 'smtp_password' => 'nullable|string', 'smtp_encryption' => 'nullable|string', 'scaleway_tem_region' => 'nullable|string', 'scaleway_tem_project_id' => 'nullable|string', 'scaleway_tem_secret_key' => 'nullable|string']);
    }

    public function smtpManagedByEnv(): bool
    {
        return config('email.managed_by_env', false);
    }

    private function secretFields(): array
    {
        return ['smtp_password', 'scaleway_tem_secret_key'];
    }

    private function ensureAllowed(): void
    {
        abort_unless(app(EnvironmentCapabilities::class)->can('edit-email-settings'), 403, 'Operazione non consentita in questa modalità.');
    }
};
?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Template email</h1></div></x-slot:header>
<form wire:submit="save" class="space-y-6">@if(session('success'))<div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif @error('smtp')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror
<div class="grid gap-6 lg:grid-cols-2"><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Provider invio email</h2>@if($this->smtpManagedByEnv())<p class="mt-4 text-sm text-content-muted">Configurato tramite variabili d'ambiente.</p>@else<div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Provider<select wire:model.live="mail_provider" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="smtp">SMTP</option><option value="scaleway_tem">Scaleway TEM</option></select></label>@if($mail_provider === 'scaleway_tem')<x-settings.input wire:model="scaleway_tem_region" label="Regione"/><x-settings.input wire:model="scaleway_tem_project_id" label="Project ID"/><x-settings.input wire:model="scaleway_tem_secret_key" type="password" label="Secret key" placeholder="Lascia vuoto per mantenere quella attuale"/>@else<x-settings.input wire:model="smtp_host" label="Host"/><div class="grid grid-cols-2 gap-4"><x-settings.input wire:model="smtp_port" label="Porta"/><label class="block text-sm font-semibold">Crittografia<select wire:model="smtp_encryption" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">Nessuna</option><option value="tls">TLS</option><option value="ssl">SSL</option></select></label></div><x-settings.input wire:model="smtp_username" label="Username"/><x-settings.input wire:model="smtp_password" type="password" label="Password" placeholder="Lascia vuoto per mantenere quella attuale"/>@endif</div>@endif</article>
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Mittente</h2><div class="mt-4 space-y-4"><x-settings.input wire:model="from_address" type="email" label="Indirizzo Reply-To"/><x-settings.input wire:model="from_name" label="Nome visualizzato"/>@if(! $this->smtpManagedByEnv())<button wire:click="testConnection" type="button" class="rounded-md border border-border px-4 py-2 text-sm font-semibold">Test connessione</button>@endif</div></article>
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Template fatture di vendita</h2><div class="mt-4 space-y-4"><x-settings.input wire:model="template_sales_subject" label="Oggetto"/><label class="block text-sm font-semibold">Corpo<textarea wire:model="template_sales_body" rows="10" class="mt-2 block w-full rounded-md border border-border px-3 py-2"></textarea></label><label class="flex gap-2 text-sm"><input wire:model="auto_send_sales" type="checkbox"> Invio automatico</label></div></article>
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Template proforma</h2><div class="mt-4 space-y-4"><x-settings.input wire:model="template_proforma_subject" label="Oggetto"/><label class="block text-sm font-semibold">Corpo<textarea wire:model="template_proforma_body" rows="10" class="mt-2 block w-full rounded-md border border-border px-3 py-2"></textarea></label><label class="flex gap-2 text-sm"><input wire:model="auto_send_proforma" type="checkbox"> Invio automatico</label></div></article></div>
@if(app(EnvironmentCapabilities::class)->can('edit-email-settings'))<button class="rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white">Salva impostazioni</button>@else<p class="text-sm text-content-muted">Configurazione in sola lettura.</p>@endif</form>
