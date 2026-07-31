<?php

use App\Actions\ManageOpenApiSettings;
use App\Contracts\EnvironmentCapabilities;
use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public bool $sandbox;
    public bool $activated;
    public bool $managedByEnv;
    public bool $demoMode;
    public bool $conservationAcknowledged;
    public bool $hasWebhookSecret;
    public string $apiToken = '';
    public string $companySdiCode = '';
    public string $webhookUrl = '';
    public string $webhookCallbackUrl = '';
    public string $simulationType = 'supplier-invoice';
    public string $invoiceUuid = '';
    public string $message = '';
    public bool $messageIsSuccess = true;

    public function mount(OpenApiSettings $settings, CompanySettings $company): void
    {
        $this->managedByEnv = (bool) config('fe-openapi.managed_by_env');
        $this->sandbox = $this->managedByEnv ? (bool) config('fe-openapi.sandbox') : $settings->sandbox;
        $this->activated = $settings->activated;
        $this->demoMode = (bool) config('demo.enabled');
        $this->conservationAcknowledged = $company->conservation_acknowledged;
        $this->hasWebhookSecret = filled($settings->webhook_secret);
        $this->companySdiCode = $this->managedByEnv ? (string) config('fe-openapi.company_sdi_code') : $settings->company_sdi_code;
        $this->webhookUrl = $this->managedByEnv ? (string) config('fe-openapi.webhook_url') : $settings->webhook_url;
        $this->refreshWebhookCallbackUrl();
    }

    public function updatedWebhookUrl(): void
    {
        $this->refreshWebhookCallbackUrl();
    }

    public function save(ManageOpenApiSettings $manager, OpenApiSettings $settings): void
    {
        $this->ensureSettingsEditable();
        $this->validateConfiguration();
        $this->applyResult($manager->save($settings, $this->apiToken, $this->sandbox, $this->companySdiCode, $this->webhookUrl));
    }

    public function checkConnection(ManageOpenApiSettings $manager, OpenApiSettings $settings, CompanySettings $company): void
    {
        $this->ensureSettingsEditable();
        $this->validateConfiguration(requireToken: false);
        $this->applyResult($manager->checkConnection($settings, $company, $this->apiToken, $this->sandbox));
    }

    public function activate(ManageOpenApiSettings $manager, OpenApiSettings $settings, CompanySettings $company): void
    {
        $this->ensureSettingsEditable();
        $this->validateConfiguration(requireToken: ! $this->managedByEnv);
        $this->applyResult($manager->activate($settings, $company, $this->apiToken, $this->sandbox, $this->companySdiCode, $this->webhookUrl));
    }

    public function deactivate(ManageOpenApiSettings $manager, OpenApiSettings $settings): void
    {
        $this->ensureSettingsEditable();
        $this->applyResult($manager->deactivate($settings));
    }

    public function simulateWebhook(ManageOpenApiSettings $manager, OpenApiSettings $settings): void
    {
        $this->ensureSettingsEditable();
        $validated = $this->validate([
            'simulationType' => 'required|in:supplier-invoice,customer-notification,customer-invoice',
            'invoiceUuid' => 'nullable|string|max:255',
        ]);
        $this->applyResult($manager->simulateWebhook($settings, $validated['simulationType'], $validated['invoiceUuid']));
    }

    public function acknowledgeConservation(CompanySettings $company): void
    {
        $company->conservation_acknowledged = true;
        $company->save();
        $this->conservationAcknowledged = true;
        $this->setMessage(__('app.conservation.acknowledged_toast'));
    }

    public function codiceDestinatario(): string
    {
        return OpenApiSdiService::CODICE_DESTINATARIO;
    }

    private function validateConfiguration(bool $requireToken = false): void
    {
        $rules = [
            'sandbox' => 'boolean',
            'companySdiCode' => 'nullable|string|max:7',
            'webhookUrl' => 'nullable|url|max:2048',
            'apiToken' => $requireToken ? 'required|string' : 'nullable|string',
        ];

        $this->validate($rules);
    }

    private function ensureSettingsEditable(): void
    {
        abort_unless(app(EnvironmentCapabilities::class)->can('edit-sdi-settings'), 403, 'Operazione non consentita in questa modalità.');
    }

    private function applyResult(array $result): void
    {
        $this->messageIsSuccess = $result['success'];
        $this->message = $result['message'];

        if (array_key_exists('activated', $result)) {
            $this->activated = $result['activated'];
        }

        if (array_key_exists('hasWebhookSecret', $result)) {
            $this->hasWebhookSecret = $result['hasWebhookSecret'];
        }

        if ($result['success'] && ! $this->managedByEnv) {
            $this->apiToken = '';
            $this->refreshWebhookCallbackUrl();
        }
    }

    private function setMessage(string $message, bool $success = true): void
    {
        $this->message = $message;
        $this->messageIsSuccess = $success;
    }

    private function refreshWebhookCallbackUrl(): void
    {
        $baseUrl = filled($this->webhookUrl) ? rtrim($this->webhookUrl, '/') : rtrim((string) config('app.url'), '/');
        $this->webhookCallbackUrl = $baseUrl.'/api/v1/openapi/webhook';
    }
};
?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Fatturazione elettronica</h1></div></x-slot:header>
<section class="space-y-6">
    @if($message)<div role="status" class="rounded-md border p-4 text-sm {{ $messageIsSuccess ? 'border-success/20 bg-success-bg text-success' : 'border-danger/20 bg-danger-bg text-danger' }}">{{ $message }}</div>@endif
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
        <div class="flex items-center justify-between"><div><h2 class="font-bold">Servizio OpenAPI</h2><p class="mt-1 text-sm text-content-muted">Codice destinatario: <strong>{{ $this->codiceDestinatario() }}</strong></p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ ($demoMode || $activated) ? 'bg-success-bg text-success' : 'bg-surface-muted text-content-muted' }}">{{ ($demoMode || $activated) ? 'Attivo' : 'Non attivo' }}</span></div>
        @if($managedByEnv || $demoMode)<p class="mt-4 text-sm text-content-muted">{{ $demoMode ? 'Modalità demo: servizio sempre attivo.' : 'Configurazione gestita dalle variabili d’ambiente.' }}</p>@else
        <div class="mt-4 grid gap-4 md:grid-cols-2"><label class="block text-sm font-semibold">API token<input wire:model="apiToken" @disabled($activated) type="password" autocomplete="new-password" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><span class="mt-1 block text-xs font-normal text-content-muted">Lascia vuoto per mantenere quello attuale.</span></label><label class="flex items-center gap-2 self-end text-sm"><input wire:model.live="sandbox" @disabled($activated) type="checkbox"> Modalità sandbox</label><label class="block text-sm font-semibold">Codice SDI<input wire:model="companySdiCode" @disabled($activated) class="mt-2 block w-full rounded-md border border-border px-3 py-2"></label><label class="block text-sm font-semibold">URL webhook base<input wire:model="webhookUrl" @disabled($activated) class="mt-2 block w-full rounded-md border border-border px-3 py-2" placeholder="https://mio-dominio.it"></label></div>
        @endif
        @if(app(EnvironmentCapabilities::class)->can('edit-sdi-settings'))<div class="mt-5 flex flex-wrap gap-2"><button wire:click="checkConnection" wire:loading.attr="disabled" wire:target="checkConnection" type="button" class="rounded-md border border-border px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="checkConnection">Verifica connessione</span><span wire:loading wire:target="checkConnection">Verifica in corso...</span></button>@if(! $activated && ! $managedByEnv && ! $demoMode)<button wire:click="save" wire:loading.attr="disabled" wire:target="save" type="button" class="rounded-md border border-border px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="save">Salva</span><span wire:loading wire:target="save">Salvataggio...</span></button>@endif@if(! $activated && ! $demoMode)<button wire:click="activate" wire:loading.attr="disabled" wire:target="activate" type="button" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="activate">Attiva</span><span wire:loading wire:target="activate">Attivazione...</span></button>@endif@if($activated && ! $demoMode)<button wire:click="deactivate" wire:confirm="Sei sicuro di voler disattivare il servizio?" wire:loading.attr="disabled" wire:target="deactivate" type="button" class="rounded-md bg-danger px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="deactivate">Disattiva</span><span wire:loading wire:target="deactivate">Disattivazione...</span></button>@endif</div>@endif
    </article>
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Webhook</h2><p class="mt-2 text-sm text-content-muted">Callback: <code>{{ $webhookCallbackUrl }}</code></p><p class="mt-2 text-sm {{ $hasWebhookSecret ? 'text-success' : 'text-content-muted' }}">{{ $hasWebhookSecret ? 'Webhook configurato.' : 'Webhook non configurato.' }}</p>@if($sandbox)<div class="mt-4 border-t pt-4"><p class="text-sm font-semibold">Simulazione sandbox</p><div class="mt-3 flex flex-wrap gap-2"><x-select wire:model="simulationType" :options="['supplier-invoice' => 'Supplier invoice', 'customer-notification' => 'Customer notification', 'customer-invoice' => 'Customer invoice']" /><input wire:model="invoiceUuid" class="rounded-md border border-border px-3 py-2 text-sm" placeholder="Invoice UUID (opzionale)"><button wire:click="simulateWebhook" wire:loading.attr="disabled" wire:target="simulateWebhook" type="button" class="rounded-md border border-border px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="simulateWebhook">Invia simulazione</span><span wire:loading wire:target="simulateWebhook">Invio...</span></button></div>@error('simulationType')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror @error('invoiceUuid')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror</div>@endif</article>
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Conservazione digitale</h2>@if($conservationAcknowledged)<p class="mt-2 text-sm text-success">Obbligo di conservazione preso in carico.</p>@else<p class="mt-2 text-sm text-content-muted">Le fatture elettroniche devono essere conservate per dieci anni.</p><div class="mt-4 flex gap-2"><x-app-link href="https://ivaservizi.agenziaentrate.gov.it" external target="_blank" rel="noopener noreferrer" class="rounded-md border border-border px-4 py-2 text-sm font-semibold">Apri Agenzia Entrate</x-app-link><button wire:click="acknowledgeConservation" wire:loading.attr="disabled" wire:target="acknowledgeConservation" type="button" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="acknowledgeConservation">Ho preso visione</span><span wire:loading wire:target="acknowledgeConservation">Aggiornamento...</span></button></div>@endif</article>
</section>
