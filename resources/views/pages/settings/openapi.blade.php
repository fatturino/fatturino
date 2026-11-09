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
    @if($message)
        <x-alert :variant="$messageIsSuccess ? 'success' : 'danger'">{{ $message }}</x-alert>
    @endif

    <x-settings.panel>
        <x-slot:heading>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-content">Servizio OpenAPI</h2>
                    <p class="mt-1 text-sm text-content-muted">Codice destinatario: <strong>{{ $this->codiceDestinatario() }}</strong></p>
                </div>
                <x-badge :value="($demoMode || $activated) ? 'Attivo' : 'Non attivo'" :variant="($demoMode || $activated) ? 'success' : 'neutral'" />
            </div>
        </x-slot:heading>

        @if($managedByEnv || $demoMode)
            <p class="text-sm text-content-muted">{{ $demoMode ? 'Modalità demo: servizio sempre attivo.' : 'Configurazione gestita dalle variabili d’ambiente.' }}</p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-settings.input wire:model="apiToken" type="password" autocomplete="new-password" :disabled="$activated" label="API token" />
                    <p class="mt-1 text-xs text-content-muted">Lascia vuoto per mantenere quello attuale.</p>
                </div>
                <label class="flex items-center gap-2 self-end text-sm text-content">
                    <input wire:model.live="sandbox" @disabled($activated) type="checkbox" class="size-4 rounded border-border text-primary focus:ring-primary/20">
                    Modalità sandbox
                </label>
                <x-settings.input wire:model="companySdiCode" :disabled="$activated" label="Codice SDI" />
                <x-settings.input wire:model="webhookUrl" :disabled="$activated" label="URL webhook base" placeholder="https://mio-dominio.it" />
            </div>
        @endif

        @if(app(EnvironmentCapabilities::class)->can('edit-sdi-settings'))
            <div class="mt-5 flex flex-wrap gap-2">
                <x-button label="Verifica connessione" wire:click="checkConnection" variant="secondary" size="cta" spinner="checkConnection" />
                @if(! $activated && ! $managedByEnv && ! $demoMode)
                    <x-button label="Salva" wire:click="save" variant="secondary" size="cta" spinner="save" />
                @endif
                @if(! $activated && ! $demoMode)
                    <x-button label="Attiva" wire:click="activate" size="cta" spinner="activate" />
                @endif
                @if($activated && ! $demoMode)
                    <x-button
                        label="Disattiva"
                        variant="danger"
                        size="cta"
                        spinner="deactivate"
                        @click="$dispatch('confirm-dialog', { message: 'Sei sicuro di voler disattivare il servizio?', callback: () => $wire.deactivate() })"
                    />
                @endif
            </div>
        @endif
    </x-settings.panel>

    <x-settings.panel title="Webhook">
        <p class="text-sm text-content-muted">Callback: <code>{{ $webhookCallbackUrl }}</code></p>
        <p @class(['mt-2 text-sm', $hasWebhookSecret ? 'text-success' : 'text-content-muted'])>
            {{ $hasWebhookSecret ? 'Webhook configurato.' : 'Webhook non configurato.' }}
        </p>
        @if($sandbox)
            <div class="mt-4 border-t border-border pt-4">
                <p class="text-sm font-semibold text-content">Simulazione sandbox</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-select wire:model="simulationType" :options="['supplier-invoice' => 'Supplier invoice', 'customer-notification' => 'Customer notification', 'customer-invoice' => 'Customer invoice']" />
                    <x-settings.input wire:model="invoiceUuid" placeholder="Invoice UUID (opzionale)" />
                    <x-button label="Invia simulazione" wire:click="simulateWebhook" variant="secondary" size="cta" spinner="simulateWebhook" />
                </div>
                @error('simulationType')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                @error('invoiceUuid')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        @endif
    </x-settings.panel>

    <x-settings.panel title="Conservazione digitale">
        @if($conservationAcknowledged)
            <p class="text-sm text-success">Obbligo di conservazione preso in carico.</p>
        @else
            <p class="text-sm text-content-muted">Le fatture elettroniche devono essere conservate per dieci anni.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button label="Apri Agenzia Entrate" link="https://ivaservizi.agenziaentrate.gov.it" variant="secondary" size="cta" target="_blank" rel="noopener noreferrer" />
                <x-button label="Ho preso visione" wire:click="acknowledgeConservation" size="cta" spinner="acknowledgeConservation" />
            </div>
        @endif
    </x-settings.panel>
</section>
